<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

use DTOKit\Data;
use DTOKit\InputData;
use DTOKit\OutputData;

/**
 * Marks a data class as lenient: unknown input fields are ignored.
 *
 * Off by default for {@see Data} and {@see OutputData};
 * applying this attribute to an {@see InputData} subclass opts out
 * of strict-by-default behavior.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class IgnoreUnknown {}
