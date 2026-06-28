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
 * Process-local mapping and serialization engine for DTOKit data objects.
 *
 * Reflects constructor metadata once per data class and reuses it for the
 * lifetime of the process, keeping behavior deterministic and free of any
 * framework container or global configuration. Provides array/object
 * mapping, recursive serialization, and explain-trace recording with safe,
 * non-sensitive context.
 *
 * @phpstan-type ParameterMeta array{name: string, input: string, output: string, type: string, nullable: bool, union: bool, default: bool, list: string|null, cast: class-string<Cast>|null, transformer: class-string<Transformer>|null, hidden: bool, sensitive: bool, redact: string|null}
 * @phpstan-type ClassMeta array{reflection: ReflectionClass<Data>, parameters: list<ParameterMeta>, strict: bool}
 */
final class Engine
{
    /**
     * Maximum supported nesting depth for mapping and serialization.
     *
     * Guards against runaway recursion from self-referential structures.
     */
    private const int MAX_DEPTH = 64;

    /** @var self|null The shared process-local instance. */
    private static ?self $instance = null;

    /** @var array<class-string<Data>, ClassMeta> Reflected metadata, keyed by class. */
    private array $metadata = [];

    /** @var list<array<string, mixed>>|null Active explain trace, or null when idle. */
    private ?array $trace = null;

    /**
     * Return the shared process-local engine instance.
     *
     * The engine caches reflected metadata per class for the lifetime of the
     * process, keeping mapping deterministic and free of container lookups.
     *
     * @return self The singleton engine.
     */
    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Map a source payload into an instance of the given data class.
     *
     * Normalizes the source and delegates to {@see mapAt()} at the root path.
     * Throws a {@see MappingException} subclass on any mapping failure.
     *
     * @param  class-string<Data>  $class  Target data class to instantiate.
     * @param  array<mixed, mixed>|object  $source  Source payload to map from.
     * @return Data The mapped, immutable data object.
     */
    public function map(string $class, array|object $source): Data
    {
        return $this->mapAt($class, $this->normalize($source), '', 0);
    }

    /**
     * Map a payload while recording a staged explain trace.
     *
     * Activates trace recording, runs {@see map()}, and returns an
     * {@see ExplainResult} containing the events, the mapped object (on
     * success), and the captured exception (on failure). Trace events never
     * include raw sensitive values.
     *
     * @param  class-string<Data>  $class  Target data class to instantiate.
     * @param  array<string, mixed>|object  $source  Source payload to explain.
     * @return ExplainResult Trace, mapped data, and any error.
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

    /**
     * Serialize a data object into a transport-ready associative array.
     *
     * Delegates to {@see serializeData()} at the root path, honoring
     * `#[Hidden]`, `#[Sensitive]`, `#[Redact]`, `#[WithTransformer]`, and
     * `#[MapOutputName]` on each property.
     *
     * @param  Data  $data  The data object to serialize.
     * @return array<string, mixed> The serialized payload.
     */
    public function serialize(Data $data): array
    {
        return $this->serializeData($data, '', 0);
    }

    /**
     * Map a normalized payload into the given data class at a nested path.
     *
     * Walks each constructor parameter, fills defaults for missing optional
     * fields, converts matched values via {@see convert()}, and rejects or
     * ignores unknown fields based on the class's strict mode. Nested paths
     * are preserved in any thrown exception. A depth guard prevents runaway
     * recursion.
     *
     * @param  class-string<Data>  $class  Target data class to instantiate.
     * @param  array<string, mixed>  $payload  Normalized source payload.
     * @param  string  $path  Current dotted path used in diagnostics.
     * @param  int  $depth  Current nesting depth, bounded by {@see MAX_DEPTH}.
     * @return Data The mapped, immutable data object.
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
     * Convert a matched value to its constructor argument type.
     *
     * Handles `null` against nullable fields, applies a custom `#[WithCast]`
     * first, then resolves `#[ListOf]` typed lists. Non-null union types
     * require an explicit cast and otherwise raise an
     * {@see AmbiguousMappingException}. Final type resolution is delegated to
     * {@see convertNamed()}.
     *
     * @param  mixed  $value  The raw value from the payload.
     * @param  ParameterMeta  $parameter  Reflected parameter metadata.
     * @param  class-string<Data>  $class  Owning data class.
     * @param  string  $path  Dotted path used in diagnostics.
     * @param  int  $depth  Current nesting depth.
     * @return mixed The converted constructor argument.
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

    /**
     * Resolve a value against a single named type.
     *
     * Recursively maps nested {@see Data} classes, instantiates backed enums
     * from their value, parses `DateTimeInterface` strings into
     * {@see DateTimeImmutable}, and lightly coerces scalars (numeric strings
     * to int/float, truthy strings to bool). Unsupported values raise a
     * {@see TypeMismatchException}.
     *
     * @param  mixed  $value  The value to convert.
     * @param  string  $type  Target type name (built-in or class-string).
     * @param  class-string<Data>  $class  Owning data class.
     * @param  string  $path  Dotted path used in diagnostics.
     * @param  int  $depth  Current nesting depth.
     * @return mixed The converted value.
     */
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

    /**
     * Serialize a data object's properties into an associative array.
     *
     * Iterates reflected parameters, skipping `#[Hidden]` fields and applying
     * `#[Redact]`/`#[Sensitive]` redaction plus `#[WithTransformer]` to
     * remaining values before recursing via {@see serializeValue()}.
     *
     * @param  Data  $data  The data object to serialize.
     * @param  string  $path  Current dotted path for diagnostics.
     * @param  int  $depth  Current nesting depth, bounded by {@see MAX_DEPTH}.
     * @return array<string, mixed> The serialized payload keyed by output name.
     */
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

    /**
     * Serialize a single value for output.
     *
     * Scalars and `null` pass through unchanged. Nested {@see Data} objects
     * are serialized recursively, backed enums emit their value, unit enums
     * emit their name, `DateTimeInterface` values format as ATOM, and arrays
     * are walked element by element. Anything else raises a
     * {@see SerializationException}.
     *
     * @param  mixed  $value  The value to serialize.
     * @param  string  $path  Current dotted path for diagnostics.
     * @param  int  $depth  Current nesting depth.
     * @return mixed The serialized value.
     */
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
     * Reflect and cache metadata for the given data class.
     *
     * Validates that the class has a public constructor with promoted typed
     * parameters, resolves strict mode (on by default for {@see InputData};
     * toggled by `#[Strict]` / `#[IgnoreUnknown]`), and collects per-parameter
     * metadata including input/output names, casts, transformers, and
     * output-affecting attributes. Results are cached per process.
     *
     * @param  class-string<Data>  $class  Target data class.
     * @return ClassMeta The reflected metadata.
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
     * Resolve a constructor parameter's type into a normalized descriptor.
     *
     * Requires a declared type and supports {@see ReflectionNamedType} plus
     * nullable union types. `self`/`static` resolve to the class itself and
     * `parent` to the parent class. Non-null intersections and other shapes
     * raise a {@see MetadataException}.
     *
     * @param  ReflectionParameter  $parameter  The constructor parameter.
     * @param  class-string<Data>  $class  Owning data class.
     * @return array{name: string, nullable: bool, union: bool} Type descriptor.
     */
    private function type(ReflectionParameter $parameter, string $class): array
    {
        $type = $parameter->getType();
        if ($type === null) {
            throw new MetadataException("{$class}::\${$parameter->getName()} must have a type.");
        }
        if ($type instanceof ReflectionNamedType) {
            return ['name' => $this->resolveTypeName($type, $class), 'nullable' => $type->allowsNull(), 'union' => false];
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

            return ['name' => implode('|', array_map(static fn (ReflectionNamedType $part): string => $part->getName(), $types)), 'nullable' => $type->allowsNull(), 'union' => true];
        }
        throw new MetadataException("Unsupported intersection type in {$class}.");
    }

    /**
     * Resolve a named type's class-string for stored metadata.
     *
     * Translates `self`/`static` to the owning class and `parent` to its
     * parent class string; other names are returned verbatim.
     *
     * @param  ReflectionNamedType  $type  The reflected type.
     * @param  class-string<Data>  $class  Owning data class.
     * @return string The resolved type name.
     */
    private function resolveTypeName(ReflectionNamedType $type, string $class): string
    {
        if ($type->getName() === 'self' || $type->getName() === 'static') {
            return $class;
        }
        if ($type->getName() === 'parent') {
            return get_parent_class($class) ?: $type->getName();
        }

        return $type->getName();
    }

    /**
     * Read a single attribute instance from a parameter or its property.
     *
     * Parameter attributes take precedence over property attributes. Returns
     * `null` when the attribute is absent. Duplicate declarations raise a
     * {@see MetadataException}.
     *
     * @template T of object
     *
     * @param  ReflectionParameter  $parameter  The constructor parameter.
     * @param  ReflectionProperty  $property  The promoted property.
     * @param  class-string<T>  $attribute  The attribute class to read.
     * @return T|null The instantiated attribute, or null.
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

    /**
     * Report whether a given attribute is present on the parameter or property.
     *
     * @param  ReflectionParameter  $parameter  The constructor parameter.
     * @param  ReflectionProperty  $property  The promoted property.
     * @param  class-string  $attribute  The attribute class to test for.
     * @return bool True when the attribute is declared.
     */
    private function hasAttribute(ReflectionParameter $parameter, ReflectionProperty $property, string $attribute): bool
    {
        return $this->attribute($parameter, $property, $attribute) !== null;
    }

    /**
     * Normalize an array or object source into a string-keyed array.
     *
     * Accepts associative arrays, `stdClass`, and objects with public
     * properties. Non-string keys raise a {@see MappingException}.
     *
     * @param  array<mixed, mixed>|object  $source  Source payload.
     * @return array<string, mixed> Normalized payload keyed by strings.
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

    /**
     * Build a {@see TypeMismatchException} with safe diagnostic context.
     *
     * The context records the class, path, stage, expected type, and the
     * received value's debug type — never the raw value itself, so sensitive
     * inputs are not leaked through errors.
     *
     * @param  class-string<Data>  $class  Owning data class.
     * @param  string  $path  Dotted path where the mismatch occurred.
     * @param  string  $expected  Expected type name.
     * @param  mixed  $value  The offending value (not included in context).
     * @return TypeMismatchException The ready-to-throw exception.
     */
    private function typeError(string $class, string $path, string $expected, mixed $value): TypeMismatchException
    {
        return new TypeMismatchException("Expected {$expected} at `{$path}`, received ".get_debug_type($value).'.', $this->context($class, $path, 'type', $expected, get_debug_type($value)));
    }

    /**
     * Assemble a safe diagnostic context array, omitting null entries.
     *
     * Only metadata (class, path, stage, expected/received type names) is
     * ever placed in context — never raw sensitive values.
     *
     * @param  class-string<Data>  $class  Owning data class.
     * @param  string  $path  Dotted path used in diagnostics.
     * @param  string  $stage  Mapping stage that produced the event.
     * @param  string|null  $expected  Expected type name, if relevant.
     * @param  string|null  $received  Received value's debug type, if relevant.
     * @return array<string, mixed> Filtered context payload.
     */
    private function context(string $class, string $path, string $stage, ?string $expected = null, ?string $received = null): array
    {
        return array_filter(['class' => $class, 'path' => $path, 'stage' => $stage, 'expected' => $expected, 'received' => $received], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Record an explain-trace event when tracing is active.
     *
     * Events are only collected while {@see explain()} is running; otherwise
     * this is a no-op. Callers must not pass raw sensitive values via context.
     *
     * @param  string  $stage  Mapping stage that produced the event.
     * @param  string  $message  Human-readable description of the event.
     * @param  array<string, mixed>  $context  Safe, non-sensitive context values.
     */
    private function event(string $stage, string $message, array $context = []): void
    {
        if ($this->trace !== null) {
            $this->trace[] = ['stage' => $stage, 'message' => $message, ...$context];
        }
    }
}
