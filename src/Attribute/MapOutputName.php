<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

/**
 * Rename the property when it is serialized to output.
 *
 * Lets the DTO accept one external name on input and emit a different
 * external name on output, decoupling inbound payloads from response
 * contracts.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class MapOutputName
{
    /**
     * @param  string  $name  External output field name to emit.
     */
    public function __construct(public string $name) {}
}
