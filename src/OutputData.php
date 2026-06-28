<?php

declare(strict_types=1);

namespace DTOKit;

use DTOKit\Attribute\Strict;

/**
 * Output-boundary base class.
 *
 * {@see OutputData} ignores unknown input fields supplied during mapping by
 * default, since outgoing shapes describe deliberate export contracts rather
 * than untrusted payloads. Override with {@see Strict} on
 * the class.
 */
abstract readonly class OutputData extends Data {}
