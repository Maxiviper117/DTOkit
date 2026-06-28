<?php

declare(strict_types=1);

namespace DTOKit\Result;

use DTOKit\Data;
use DTOKit\Exception\MappingException;

final readonly class ExplainResult
{
    /** @param list<array<string, mixed>> $events */
    public function __construct(public array $events, public ?Data $data, public ?MappingException $error) {}

    public function succeeded(): bool
    {
        return $this->data instanceof Data;
    }

    /** @return array{success: bool, events: list<array<string, mixed>>, error: array<string, mixed>|null} */
    public function toArray(): array
    {
        return ['success' => $this->succeeded(), 'events' => $this->events, 'error' => $this->error?->context];
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $flags);
    }

    public function toText(): string
    {
        return implode("\n", array_map(static function (array $event): string {
            $stage = is_string($event['stage'] ?? null) ? $event['stage'] : 'unknown';
            $message = is_string($event['message'] ?? null) ? $event['message'] : '';

            return "[{$stage}] {$message}";
        }, $this->events));
    }
}
