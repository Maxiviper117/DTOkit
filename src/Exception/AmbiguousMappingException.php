<?php

declare(strict_types=1);

namespace DTOKit\Exception;

use DTOKit\Attribute\WithCast;

/**
 * Thrown when a non-null union type is encountered without an explicit cast.
 *
 * Non-null union types are ambiguous: the engine cannot pick a member, so a
 * {@see WithCast} is required to disambiguate.
 */
final class AmbiguousMappingException extends MappingException {}
