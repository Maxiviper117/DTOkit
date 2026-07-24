<?php

declare(strict_types=1);

namespace DTOKit\Exception;

/**
 * Thrown when a payload cannot be mapped into a data object.
 *
 * Carries a safe, non-sensitive `context` array (class, path, stage, and
 * type metadata) that callers can use for diagnostics. Subclasses describe
 * specific mapping failures.
 */
class MappingException extends DTOKitException
{
    /**
     * @param  string  $message  Human-readable description of the failure.
     * @param  array<string, mixed>  $context  Safe, non-sensitive diagnostic context.
     * @param  \Throwable|null  $previous  Optional prior exception to chain.
     */
    public function __construct(string $message, public readonly array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
