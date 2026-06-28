<?php

declare(strict_types=1);

namespace DTOKit\Exception;

/**
 * Thrown when serialization fails: unsupported value types, depth exceeded,
 * or a transformer raised an error.
 */
class SerializationException extends DTOKitException {}
