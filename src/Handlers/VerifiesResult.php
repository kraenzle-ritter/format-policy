<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Handlers;

use KraenzleRitter\FormatPolicy\Command;
use KraenzleRitter\FormatPolicy\Verification;

/**
 * Handler of a `verify` command: does the result keep what the rule promises?
 */
interface VerifiesResult
{
    public function verify(Command $command, string $binary, string $original, string $result): Verification;
}
