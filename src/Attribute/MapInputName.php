<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

/**
 * Rename the input field expected during mapping.
 *
 * Allows the constructor property to keep a PHP-style name while accepting
 * payloads that use a different external name (for example snake_case).
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class MapInputName
{
    /**
     * @param  string  $name  External input field name to accept.
     */
    public function __construct(public string $name) {}
}
