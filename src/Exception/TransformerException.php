<?php

declare(strict_types=1);

namespace DTOKit\Exception;

use DTOKit\Attribute\WithTransformer;

/**
 * Thrown when a {@see WithTransformer} implementation
 * raises an error during serialization. Wraps the original throwable.
 */
final class TransformerException extends SerializationException {}
