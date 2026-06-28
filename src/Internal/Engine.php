<?php

declare(strict_types=1);

namespace DTOKit\Internal;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DTOKit\Attribute\Hidden;
use DTOKit\Attribute\IgnoreUnknown;
use DTOKit\Attribute\ListOf;
use DTOKit\Attribute\MapInputName;
use DTOKit\Attribute\MapOutputName;
use DTOKit\Attribute\Redact;
use DTOKit\Attribute\Sensitive;
use DTOKit\Attribute\Strict;
use DTOKit\Attribute\WithCast;
use DTOKit\Attribute\WithTransformer;
use DTOKit\Contract\Cast;
use DTOKit\Contract\Transformer;
use DTOKit\Data;
use DTOKit\Exception\AmbiguousMappingException;
use DTOKit\Exception\CastException;
use DTOKit\Exception\MappingException;
use DTOKit\Exception\MetadataException;
use DTOKit\Exception\MissingRequiredFieldException;
use DTOKit\Exception\SerializationException;
use DTOKit\Exception\TransformerException;
use DTOKit\Exception\TypeMismatchException;
use DTOKit\Exception\UnknownFieldException;
use DTOKit\InputData;
use DTOKit\Result\ExplainResult;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;
use UnitEnum;

/**
 * @phpstan-type ParameterMeta array{name: string, input: string, output: string, type: string, nullable: bool, union: bool, default: bool, list: string|null, cast: class-string<Cast>|null, transformer: class-string<Transformer>|null, hidden: bool, sensitive: bool, redact: string|null}
 * @phpstan-type ClassMeta array{reflection: ReflectionClass<Data>, parameters: list<ParameterMeta>, strict: bool}
 */
final class Engine
{
    private const int MAX_DEPTH = 64;

    private static ?self $instance = null;

    /** @var array<class-string<Data>, ClassMeta> */
    private array $metadata = [];

    /** @var list<array<string, mixed>>|null */
    private ?array $trace = null;

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * @param  class-string<Data>  $class
     * @param  array<string, mixed>|object  $source
     */
    public function map(string $class, array|object $source): Data
    {
        return $this->mapAt($class, $this->normalize($source), '', 0);
    }

    /**
     * @param  class-string<Data>  $class
     * @param  array<string, mixed>|object  $source
     */
    public function explain(string $class, array|object $source): ExplainResult
    {
        $this->trace = [];
        try {
            $this->event('source', 'Normalized input source', ['type' => get_debug_type($source)]);
            $data = $this->map($class, $source);
            $this->event('result', 'Mapping succeeded', ['class' => $class]);

            return new ExplainResult($this->trace ?? [], $data, null);
        } catch (MappingException $error) {
            $this->event('result', 'Mapping failed', $error->context);

            return new ExplainResult($this->trace ?? [], null, $error);
        } finally {
            $this->trace = null;
        }
    }

    /** @return array<string, mixed> */
    public function serialize(Data $data): array
    {
        return $this->serializeData($data, '', 0);
    }

    /**
     * @param  class-string<Data>  $class
     * @param  array<string, mixed>  $payload
     */
    private function mapAt(string $class, array $payload, string $path, int $depth): Data
    {
        if ($depth > self::MAX_DEPTH) {
            throw new MappingException('Maximum mapping depth exceeded.', $this->context($class, $path, 'mapping'));
        }
        $meta = $this->metadata($class);
        $arguments = [];
        $used = [];
        foreach ($meta['parameters'] as $parameter) {
            $key = $parameter['input'];
            $fieldPath = $path === '' ? $key : $path.'.'.$key;
            if (! array_key_exists($key, $payload)) {
                if ($parameter['default']) {
                    $this->event('default', 'Used constructor default', ['path' => $fieldPath]);

                    continue;
                }
                throw new MissingRequiredFieldException("Missing required field `{$fieldPath}`.", $this->context($class, $fieldPath, 'constructor', $parameter['type']));
            }
            $used[$key] = true;
            $value = $payload[$key];
            if ($parameter['sensitive']) {
                $this->event('match', 'Matched sensitive input', ['path' => $fieldPath, 'value' => '[redacted]']);
            } else {
                $this->event('match', 'Matched input field', ['path' => $fieldPath]);
            }
            $arguments[$parameter['name']] = $this->convert($value, $parameter, $class, $fieldPath, $depth + 1);
        }
        $unknown = array_values(array_diff(array_keys($payload), array_keys($used)));
        if ($unknown !== []) {
            $unknownPaths = array_map(static fn (string|int $key): string => $path === '' ? (string) $key : $path.'.'.$key, $unknown);
            $this->event('unknown', $meta['strict'] ? 'Rejected unknown fields' : 'Ignored unknown fields', ['paths' => $unknownPaths]);
            if ($meta['strict']) {
                throw new UnknownFieldException('Unknown input field(s): '.implode(', ', $unknownPaths).'.', $this->context($class, $path, 'unknown_fields') + ['fields' => $unknownPaths]);
            }
        }
        try {
            return $meta['reflection']->newInstanceArgs($arguments);
        } catch (\Throwable $error) {
            throw new MappingException("Could not construct {$class}.", $this->context($class, $path, 'constructor'), $error);
        }
    }

    /**
     * @param  ParameterMeta  $parameter
     * @param  class-string<Data>  $class
     */
    private function convert(mixed $value, array $parameter, string $class, string $path, int $depth): mixed
    {
        if ($value === null) {
            if ($parameter['nullable']) {
                return null;
            }
            throw $this->typeError($class, $path, $parameter['type'], $value);
        }
        if ($parameter['cast'] !== null) {
            try {
                $cast = new $parameter['cast'];
                $value = $cast->cast($value);
                $this->event('cast', 'Applied custom cast', ['path' => $path, 'cast' => $parameter['cast']]);
            } catch (\Throwable $error) {
                throw new CastException("Cast failed at `{$path}`.", $this->context($class, $path, 'cast', $parameter['type']), $error);
            }
        }
        if ($parameter['list'] !== null) {
            if (! is_array($value) || ! array_is_list($value)) {
                throw $this->typeError($class, $path, 'list<'.$parameter['list'].'>', $value);
            }
            $result = [];
            foreach ($value as $index => $item) {
                $result[] = $this->convertNamed($item, $parameter['list'], $class, $path.'.'.$index, $depth);
            }

            return $result;
        }
        if ($parameter['union']) {
            throw new AmbiguousMappingException("Non-null union type at `{$path}` requires an explicit cast.", $this->context($class, $path, 'type', $parameter['type']));
        }

        return $this->convertNamed($value, $parameter['type'], $class, $path, $depth);
    }

    /** @param class-string<Data> $class */
    private function convertNamed(mixed $value, string $type, string $class, string $path, int $depth): mixed
    {
        if (is_a($type, Data::class, true)) {
            if ($value instanceof $type) {
                return $value;
            }
            if (! is_array($value) && ! is_object($value)) {
                throw $this->typeError($class, $path, $type, $value);
            }

            return $this->mapAt($type, $this->normalize($value), $path, $depth);
        }
        if (is_a($type, BackedEnum::class, true)) {
            if ($value instanceof $type) {
                return $value;
            }
            if (! is_int($value) && ! is_string($value)) {
                throw $this->typeError($class, $path, $type, $value);
            }
            try {
                return $type::from($value);
            } catch (\Throwable) {
                throw $this->typeError($class, $path, $type, $value);
            }
        }
        if (is_a($type, DateTimeInterface::class, true)) {
            if ($value instanceof DateTimeInterface) {
                return $value;
            }
            if (! is_string($value)) {
                throw $this->typeError($class, $path, $type, $value);
            }
            try {
                return new DateTimeImmutable($value);
            } catch (\Throwable) {
                throw $this->typeError($class, $path, $type, $value);
            }
        }
        $converted = match ($type) {
            'mixed' => $value,
            'string' => is_string($value) ? $value : null,
            'int' => is_int($value) ? $value : (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1 ? (int) $value : null),
            'float' => is_float($value) || is_int($value) ? (float) $value : (is_string($value) && is_numeric($value) ? (float) $value : null),
            'bool' => is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'array' => is_array($value) ? $value : null,
            'object' => is_object($value) ? $value : null,
            default => $value instanceof $type ? $value : null,
        };
        if ($converted === null) {
            throw $this->typeError($class, $path, $type, $value);
        }

        return $converted;
    }

    /** @return array<string, mixed> */
    private function serializeData(Data $data, string $path, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new SerializationException('Maximum serialization depth exceeded.');
        }
        $meta = $this->metadata($data::class);
        $result = [];
        foreach ($meta['parameters'] as $parameter) {
            if ($parameter['hidden']) {
                continue;
            }
            $value = $data->{$parameter['name']};
            if ($parameter['redact'] !== null || $parameter['sensitive']) {
                $value = $parameter['redact'] ?? '[redacted]';
            } elseif ($parameter['transformer'] !== null) {
                try {
                    $transformer = new $parameter['transformer'];
                    $value = $transformer->transform($value);
                } catch (\Throwable $error) {
                    throw new TransformerException('Transformer failed at `'.$path.$parameter['name'].'`.', 0, $error);
                }
            }
            $result[$parameter['output']] = $this->serializeValue($value, $path.$parameter['output'], $depth + 1);
        }

        return $result;
    }

    private function serializeValue(mixed $value, string $path, int $depth): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        if ($value instanceof Data) {
            return $this->serializeData($value, $path.'.', $depth);
        }
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->serializeValue($item, $path.'.'.$key, $depth + 1);
            }

            return $result;
        }
        throw new SerializationException("Unsupported value at `{$path}`: ".get_debug_type($value).'.');
    }

    /**
     * @param  class-string<Data>  $class
     * @return ClassMeta
     */
    private function metadata(string $class): array
    {
        if (isset($this->metadata[$class])) {
            return $this->metadata[$class];
        }
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor === null || ! $constructor->isPublic()) {
            throw new MetadataException("{$class} must declare a public constructor.");
        }
        $strict = is_a($class, InputData::class, true);
        if ($reflection->getAttributes(Strict::class) !== []) {
            $strict = true;
        }
        if ($reflection->getAttributes(IgnoreUnknown::class) !== []) {
            $strict = false;
        }
        $parameters = [];
        $inputNames = [];
        foreach ($constructor->getParameters() as $parameter) {
            if (! $parameter->isPromoted()) {
                throw new MetadataException("{$class} constructor parameters must be promoted properties.");
            }
            $property = $reflection->getProperty($parameter->getName());
            $type = $this->type($parameter, $class);
            $inputAttribute = $this->attribute($parameter, $property, MapInputName::class);
            $input = $inputAttribute === null ? $parameter->getName() : $inputAttribute->name;
            if (isset($inputNames[$input])) {
                throw new MetadataException("Duplicate input name `{$input}` in {$class}.");
            }
            $inputNames[$input] = true;
            $outputAttribute = $this->attribute($parameter, $property, MapOutputName::class);
            $parameters[] = ['name' => $parameter->getName(), 'input' => $input,
                'output' => $outputAttribute === null ? $parameter->getName() : $outputAttribute->name,
                'type' => $type['name'], 'nullable' => $type['nullable'], 'union' => $type['union'],
                'default' => $parameter->isDefaultValueAvailable(), 'list' => $this->attribute($parameter, $property, ListOf::class)?->type,
                'cast' => $this->attribute($parameter, $property, WithCast::class)?->cast,
                'transformer' => $this->attribute($parameter, $property, WithTransformer::class)?->transformer,
                'hidden' => $this->hasAttribute($parameter, $property, Hidden::class),
                'sensitive' => $this->hasAttribute($parameter, $property, Sensitive::class),
                'redact' => $this->attribute($parameter, $property, Redact::class)?->replacement];
        }

        return $this->metadata[$class] = ['reflection' => $reflection, 'parameters' => $parameters, 'strict' => $strict];
    }

    /**
     * @param  class-string<Data>  $class
     * @return array{name: string, nullable: bool, union: bool}
     */
    private function type(ReflectionParameter $parameter, string $class): array
    {
        $type = $parameter->getType();
        if ($type === null) {
            throw new MetadataException("{$class}::\${$parameter->getName()} must have a type.");
        }
        if ($type instanceof ReflectionNamedType) {
            return ['name' => $type->getName(), 'nullable' => $type->allowsNull(), 'union' => false];
        }
        if ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $part) {
                if (! $part instanceof ReflectionNamedType) {
                    throw new MetadataException("Unsupported intersection union in {$class}.");
                }
                if ($part->getName() !== 'null') {
                    $types[] = $part;
                }
            }
            if (count($types) === 1) {
                return ['name' => $types[0]->getName(), 'nullable' => true, 'union' => false];
            }

            return ['name' => implode('|', array_map(static fn (ReflectionNamedType $part): string => $part->getName(), $types)), 'nullable' => $type->allowsNull(), 'union' => true];
        }
        throw new MetadataException("Unsupported intersection type in {$class}.");
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return T|null
     */
    private function attribute(ReflectionParameter $parameter, ReflectionProperty $property, string $attribute): ?object
    {
        $attributes = $parameter->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);
        if ($attributes === []) {
            $attributes = $property->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);
        }
        if ($attributes === []) {
            return null;
        }
        if (count($attributes) > 1) {
            throw new MetadataException("Attribute {$attribute} is duplicated on {$property->getDeclaringClass()->getName()}::\${$property->getName()}.");
        }

        return $attributes[0]->newInstance();
    }

    /** @param class-string $attribute */
    private function hasAttribute(ReflectionParameter $parameter, ReflectionProperty $property, string $attribute): bool
    {
        return $this->attribute($parameter, $property, $attribute) !== null;
    }

    /**
     * @param  array<mixed, mixed>|object  $source
     * @return array<string, mixed>
     */
    private function normalize(array|object $source): array
    {
        $values = is_array($source) ? $source : get_object_vars($source);
        $normalized = [];
        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                throw new MappingException('Input field names must be strings.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @param class-string<Data> $class */
    private function typeError(string $class, string $path, string $expected, mixed $value): TypeMismatchException
    {
        return new TypeMismatchException("Expected {$expected} at `{$path}`, received ".get_debug_type($value).'.', $this->context($class, $path, 'type', $expected, get_debug_type($value)));
    }

    /**
     * @param  class-string<Data>  $class
     * @return array<string, mixed>
     */
    private function context(string $class, string $path, string $stage, ?string $expected = null, ?string $received = null): array
    {
        return array_filter(['class' => $class, 'path' => $path, 'stage' => $stage, 'expected' => $expected, 'received' => $received], static fn (mixed $value): bool => $value !== null);
    }

    /** @param array<string, mixed> $context */
    private function event(string $stage, string $message, array $context = []): void
    {
        if ($this->trace !== null) {
            $this->trace[] = ['stage' => $stage, 'message' => $message, ...$context];
        }
    }
}
