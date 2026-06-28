<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class Redact
{
    public function __construct(public string $replacement = '[redacted]') {}
}
