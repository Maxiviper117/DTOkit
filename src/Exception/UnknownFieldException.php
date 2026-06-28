<?php

declare(strict_types=1);

namespace DTOKit\Exception;

/**
 * Thrown when a strict data class receives input fields it does not declare.
 *
 * The context's `fields` array lists the unknown dotted paths.
 */
final class UnknownFieldException extends MappingException {}
