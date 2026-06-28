<?php

declare(strict_types=1);

namespace DTOKit\Result;

use DTOKit\Data;
use DTOKit\Exception\MappingException;

final readonly class MappingResult
{
    private function __construct(public ?Data $data, public ?MappingException $error) {}

    public static function success(Data $data): self
    {
        return new self($data, null);
    }

    public static function failure(MappingException $error): self
    {
        return new self(null, $error);
    }

    public function succeeded(): bool
    {
        return $this->data instanceof Data;
    }

    public function failed(): bool
    {
        return $this->error instanceof MappingException;
    }
}
