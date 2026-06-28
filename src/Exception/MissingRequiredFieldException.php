<?php

declare(strict_types=1);

namespace DTOKit\Exception;

/**
 * Thrown when a required constructor parameter is missing from the payload
 * and has no default value.
 */
final class MissingRequiredFieldException extends MappingException {}
