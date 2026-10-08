<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Handlers;

use KraenzleRitter\FormatPolicy\Command;
use KraenzleRitter\FormatPolicy\Identification;

/**
 * Handler of an `identify` command.
 */
interface IdentifiesFormat
{
    public function identify(Command $command, string $binary, string $file): Identification;
}
