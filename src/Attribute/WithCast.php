<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

use DTOKit\Contract\Cast;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class WithCast
{
    /** @param class-string<Cast> $cast */
    public function __construct(public string $cast) {}
}
