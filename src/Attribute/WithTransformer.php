<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

use DTOKit\Contract\Transformer;

/**
 * Apply a container-free {@see Transformer} to the value during serialization.
 *
 * The transformer class is instantiated with `new` and invoked after any
 * redaction logic, so it can shape the outgoing value for transport.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class WithTransformer
{
    /**
     * @param  class-string<Transformer>  $transformer  Transformer implementation to instantiate.
     */
    public function __construct(public string $transformer) {}
}
