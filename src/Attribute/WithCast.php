<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

use DTOKit\Contract\Cast;

/**
 * Apply a container-free {@see Cast} to the raw input value during mapping.
 *
 * The cast class is instantiated with `new` and invoked before type
 * resolution, so it can normalize or rewrite values that the engine could
 * not otherwise coerce.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class WithCast
{
    /**
     * @param  class-string<Cast>  $cast  Cast implementation to instantiate.
     */
    public function __construct(public string $cast) {}
}
