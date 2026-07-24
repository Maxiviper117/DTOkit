<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

/**
 * Mark the property as sensitive.
 *
 * On serialization the value is replaced with `[redacted]`, and explain-trace
 * events never emit the raw value. Use {@see Redact} to customize the
 * replacement string.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class Sensitive {}
