<?php

declare(strict_types=1);

namespace DTOKit\Exception;

/**
 * Thrown when a value does not match the declared type at a mapping path.
 *
 * The context records the expected type and the received value's debug type,
 * never the raw value itself.
 */
final class TypeMismatchException extends MappingException {}
