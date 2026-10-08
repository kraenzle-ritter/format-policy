<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Handlers;

use KraenzleRitter\FormatPolicy\Command;

/**
 * Handler of a `characterize` command: the facts a rule's `skip_when` reads.
 */
interface CharacterizesFile
{
    /**
     * @return array<string, scalar|null>
     */
    public function characterize(Command $command, string $binary, string $file): array;
}
