<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Handlers;

use KraenzleRitter\FormatPolicy\Command;

/**
 * Handler of a `transform` command. Throws when it fails; the caller then
 * ingests the original.
 */
interface TransformsFile
{
    /**
     * @param  string  $original  the file as delivered
     * @param  string  $source  the file before this step
     * @param  string  $target  the file this step writes (in place: same as $source)
     */
    public function transform(Command $command, string $binary, string $original, string $source, string $target): void;
}
