<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

use DTOKit\Data;

/**
 * Omit the marked property from serialized output.
 *
 * Honored by {@see Data::toArray()} and {@see Data::toJson()}.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class Hidden {}
