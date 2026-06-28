<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

use DTOKit\Data;

/**
 * Declare the element type of a list-valued constructor property.
 *
 * The engine validates that the value is a list (`array_is_list`) and maps
 * each element to the given class, which may itself be a {@see Data}
 * subclass, a backed enum, or another supported type.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class ListOf
{
    /**
     * @param  string  $type  Class-string or built-in type of each list element.
     */
    public function __construct(public string $type) {}
}
