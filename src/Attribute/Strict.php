<?php

declare(strict_types=1);

namespace DTOKit\Attribute;

use DTOKit\Data;
use DTOKit\InputData;
use DTOKit\OutputData;

/**
 * Marks a data class as strict: unknown input fields are rejected.
 *
 * On by default for {@see InputData}; applying this attribute to a
 * {@see Data} or {@see OutputData} subclass opts into the
 * same behavior.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Strict {}
