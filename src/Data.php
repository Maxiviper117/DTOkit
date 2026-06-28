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
    /** @param array<string, mixed>|object $source */
    final public static function from(array|object $source): static
    {
        /** @var static $data */
        $data = Engine::instance()->map(static::class, $source);

        return $data;
    }

    /** @param array<string, mixed>|object $source */
    final public static function tryFrom(array|object $source): MappingResult
    {
        try {
            return MappingResult::success(self::from($source));
        } catch (MappingException $exception) {
            return MappingResult::failure($exception);
        }
    }

    /** @param array<string, mixed>|object $source */
    final public static function explain(array|object $source): ExplainResult
    {
        return Engine::instance()->explain(static::class, $source);
    }

    /** @return array<string, mixed> */
    final public function toArray(): array
    {
        return Engine::instance()->serialize($this);
    }

    final public function toJson(int $flags = 0): string
    {
        try {
            return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $flags);
        } catch (\JsonException $exception) {
            throw new SerializationException('JSON encoding failed.', 0, $exception);
        }
    }
}
