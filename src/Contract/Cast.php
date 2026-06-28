<?php

declare(strict_types=1);

namespace DTOKit\Contract;

use DTOKit\Attribute\WithCast;

/**
 * Container-free input transformation invoked during mapping.
 *
 * Implementations are instantiated with `new` by the engine and applied to
 * a raw value before type resolution, via the {@see WithCast}
 * attribute. Implementations must be deterministic and must not rely on any
 * service container or global state.
 */
interface Cast
{
    /**
     * Convert a raw input value into a shape the engine can map.
     *
     * @param  mixed  $value  The raw value from the payload.
     * @return mixed The normalized value to feed into type resolution.
     */
    public function cast(mixed $value): mixed;
}
