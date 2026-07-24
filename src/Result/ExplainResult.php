<?php

declare(strict_types=1);

namespace DTOKit\Result;

use DTOKit\Data;
use DTOKit\Exception\MappingException;

/**
 * Value object describing the outcome of an explainable mapping run.
 *
 * Returned by {@see Data::explain()}. Combines a list of staged
 * trace events with either a mapped object (on success) or a captured
 * exception (on failure). Render via {@see toArray()}, {@see toJson()}, or
 * {@see toText()}.
 */
final readonly class ExplainResult
{
    /**
     * @param  list<array<string, mixed>>  $events  Staged trace events.
     * @param  Data|null  $data  The mapped object on success.
     * @param  MappingException|null  $error  The captured exception on failure.
     */
    public function __construct(public array $events, public ?Data $data, public ?MappingException $error) {}

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
     * Render the trace as an associative array.
     *
     * Includes the success flag, the staged events, and the safe exception
     * context (if any). Raw sensitive values are never included.
     *
     * @return array{success: bool, events: list<array<string, mixed>>, error: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return ['success' => $this->succeeded(), 'events' => $this->events, 'error' => $this->error?->context];
    }

    /**
     * Render the trace as a JSON string.
     *
     * Encodes {@see toArray()} with `JSON_THROW_ON_ERROR` plus any additional
     * flags supplied by the caller.
     *
     * @param  int  $flags  Bitmask of additional JSON_* flags to apply.
     * @return string The JSON-encoded trace.
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $flags);
    }

    /**
     * Render the trace as a plain-text log of staged events.
     *
     * Each event is rendered as `[stage] message`, one per line. Suitable for
     * logging or developer diagnostics without exposing sensitive values.
     *
     * @return string Multi-line, human-readable trace.
     */
    public function toText(): string
    {
        return implode("\n", array_map(static function (array $event): string {
            $stage = is_string($event['stage'] ?? null) ? $event['stage'] : 'unknown';
            $message = is_string($event['message'] ?? null) ? $event['message'] : '';

            return "[{$stage}] {$message}";
        }, $this->events));
    }
}
