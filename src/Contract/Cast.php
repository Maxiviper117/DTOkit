<?php

declare(strict_types=1);

namespace DTOKit\Contract;

interface Cast
{
    public function cast(mixed $value): mixed;
}
