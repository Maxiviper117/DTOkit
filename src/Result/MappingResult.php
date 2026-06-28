<?php

declare(strict_types=1);

namespace DTOKit\Result;

use DTOKit\Data;
use DTOKit\Exception\MappingException;

/**
 * Value object representing the outcome of a non-throwing mapping attempt.
 *
 * Returned by {@see Data::tryFrom()}. Exactly one of {@see $data} or
 * {@see $error} is set.
 */
final readonly class MappingResult
{
    /**
     * @param  Data|null  $data  The mapped object on success.
     * @param  MappingException|null  $error  The captured exception on failure.
     */
    private function __construct(public ?Data $data, public ?MappingException $error) {}

    /**
     * Construct a successful result.
     *
     * @param  Data  $data  The mapped object.
     * @return self A success result wrapping the data.
     */
    public static function success(Data $data): self
    {
        return new self($data, null);
    }

    /**
     * Construct a failed result.
     *
     * @param  MappingException  $error  The captured mapping exception.
     * @return self A failure result wrapping the error.
     */
    public static function failure(MappingException $error): self
    {
        return new self(null, $error);
    }

    /**
     * Report whether mapping produced a data object.
     *
     * @return bool True when mapping succeeded.
     */
    public function succeeded(): bool
    {
        return $this->data instanceof Data;
    }

    /**
     * Report whether mapping captured an exception.
     *
     * @return bool True when mapping failed.
     */
    public function failed(): bool
    {
        return $this->error instanceof MappingException;
    }
}
