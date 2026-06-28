<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

use DTOKit\Contract\Transformer;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class WithTransformer
{
    /** @param class-string<Transformer> $transformer */
    public function __construct(public string $transformer) {}
}
