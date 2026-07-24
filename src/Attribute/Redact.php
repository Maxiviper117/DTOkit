<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

/**
 * Redact the property on serialization with a custom replacement string.
 *
 * Like {@see Sensitive}, but the replacement value is configurable. The raw
 * value is also never emitted in explain traces.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class Redact
{
    /**
     * @param  string  $replacement  Replacement string emitted on serialization.
     */
    public function __construct(public string $replacement = '[redacted]') {}
}
