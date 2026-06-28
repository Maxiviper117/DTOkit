<?php

declare(strict_types=1);

namespace DTOKit\Contract;

interface Transformer
{
    public function transform(mixed $value): mixed;
}
