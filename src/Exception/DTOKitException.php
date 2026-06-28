<?php

declare(strict_types=1);

namespace DTOKit\Exception;

/**
 * Base exception for all DTOKit errors.
 *
 * All DTOKit exceptions extend this class so callers can catch the toolkit's
 * failures as a single category.
 */
class DTOKitException extends \RuntimeException {}
