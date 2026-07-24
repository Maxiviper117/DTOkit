<?php

declare(strict_types=1);

namespace DTOKit\Exception;

/**
 * Thrown when a data class's reflected shape is unsupported: missing public
 * constructor, non-promoted parameters, untyped parameters, duplicate input
 * names, or duplicate attributes.
 */
final class MetadataException extends DTOKitException {}
