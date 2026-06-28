<?php

declare(strict_types=1);

namespace DTOKit;

use DTOKit\Exception\MappingException;
use DTOKit\Exception\SerializationException;
use DTOKit\Internal\Engine;
use DTOKit\Result\ExplainResult;
use DTOKit\Result\MappingResult;

abstract readonly class Data
{
    /**
     * Map an array or object payload into an immutable instance of this class.
     *
     * Throws a {@see MappingException} subclass when the payload is missing
     * required fields, contains type mismatches, or — for strict classes —
     * declares unknown fields.
     *
     * @param  array<string, mixed>|object  $source  Source payload to map from.
     * @return static The mapped, immutable data object.
     */
    final public static function from(array|object $source): static
    {
        /** @var static $data */
        $data = Engine::instance()->map(static::class, $source);

        return $data;
    }

    /**
     * Attempt to map a payload without throwing.
     *
     * Wraps {@see from()} so that mapping failure is represented as a
     * {@see MappingResult} value instead of an exception. Useful when invalid
     * input is expected control flow.
     *
     * @param  array<string, mixed>|object  $source  Source payload to map from.
     * @return MappingResult Success or failure result.
     */
    final public static function tryFrom(array|object $source): MappingResult
    {
        try {
            return MappingResult::success(self::from($source));
        } catch (MappingException $exception) {
            return MappingResult::failure($exception);
        }
    }

    /**
     * Map a payload and produce a safe, explainable trace of the mapping.
     *
     * Returns an {@see ExplainResult} containing the mapped object (on
     * success) plus a list of staged events describing how the engine reached
     * the result. Trace events never include raw sensitive values.
     *
     * @param  array<string, mixed>|object  $source  Source payload to explain.
     * @return ExplainResult Trace, mapped data, and any error.
     */
    final public static function explain(array|object $source): ExplainResult
    {
        return Engine::instance()->explain(static::class, $source);
    }

    /**
     * Serialize this object into a transport-ready associative array.
     *
     * Honors `#[Hidden]`, `#[Sensitive]`, `#[Redact]`, `#[WithTransformer]`,
     * and `#[MapOutputName]`. Nested {@see Data} objects, enums, and
     * `DateTimeInterface` values are serialized recursively.
     *
     * @return array<string, mixed> The serialized payload.
     */
    final public function toArray(): array
    {
        return Engine::instance()->serialize($this);
    }

    /**
     * Serialize this object to a JSON string.
     *
     * Encodes the result of {@see toArray()} with `JSON_THROW_ON_ERROR` plus
     * any additional flags. JSON encoding failures are wrapped in a
     * {@see SerializationException}.
     *
     * @param  int  $flags  Bitmask of additional JSON_* flags to apply.
     * @return string The JSON-encoded payload.
     */
    final public function toJson(int $flags = 0): string
    {
        try {
            return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $flags);
        } catch (\JsonException $exception) {
            throw new SerializationException('JSON encoding failed.', 0, $exception);
        }
    }
}
