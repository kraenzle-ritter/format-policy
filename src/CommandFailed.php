<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

use RuntimeException;

/**
 * A command of the format policy could not do its work: program missing,
 * non-zero exit, time limit, no output. The preingest then ingests the
 * original.
 */
final class CommandFailed extends RuntimeException {}
