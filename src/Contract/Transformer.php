<?php

declare(strict_types=1);

namespace DTOKit\Contract;

use DTOKit\Attribute\WithTransformer;

/**
 * Container-free output transformation invoked during serialization.
 *
 * Implementations are instantiated with `new` by the engine and applied to
 * a value after any redaction logic, via the
 * {@see WithTransformer} attribute. Implementations must be
 * deterministic and must not rely on any service container or global state.
 */
interface Transformer
{
    /**
     * Transform a value into its serialized representation.
     *
     * @param  mixed  $value  The value held on the data object.
     * @return mixed The value to emit in serialized output.
     */
    public function transform(mixed $value): mixed;
}
