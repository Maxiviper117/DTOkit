<?php

declare(strict_types=1);

namespace DTOKit\Exception;

use DTOKit\Attribute\WithCast;

/**
 * Thrown when a {@see WithCast} implementation raises an
 * error during mapping. Wraps the original throwable.
 */
final class CastException extends MappingException {}
