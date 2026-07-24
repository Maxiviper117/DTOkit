<?php

declare(strict_types=1);

namespace DTOKit;

use DTOKit\Attribute\IgnoreUnknown;

/**
 * Strict, input-boundary base class.
 *
 * {@see InputData} rejects unknown input fields by default to prevent
 * accidental acceptance of undeclared payload values. Override with
 * {@see IgnoreUnknown} on the class.
 */
abstract readonly class InputData extends Data {}
