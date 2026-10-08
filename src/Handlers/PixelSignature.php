<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Handlers;

use Illuminate\Support\Facades\Process;
use KraenzleRitter\FormatPolicy\Command;
use KraenzleRitter\FormatPolicy\CommandFailed;
use KraenzleRitter\FormatPolicy\Verification;

/**
 * Proves a lossless conversion: ImageMagick's signature of the decoded pixels
 * (`%#`) of every page, original against result. Same number of pages, same
 * signature on each.
 *
 * ImageMagick 7 is `magick identify`, version 6 a plain `identify`; the tool
 * names both and the handler follows whichever this server has.
 */
final class PixelSignature implements VerifiesResult
{
    public function verify(Command $command, string $binary, string $original, string $result): Verification
    {
        $expected = $this->signatures($command, $binary, $original);
        $actual = $this->signatures($command, $binary, $result);

        if (count($expected) !== count($actual)) {
            return new Verification(false, sprintf('%d pages delivered, %d in the result', count($expected), count($actual)));
        }

        foreach ($expected as $page => $signature) {
            if ($actual[$page] !== $signature) {
                return new Verification(false, sprintf('the pixels differ on page %d', $page + 1));
            }
        }

        return new Verification(true, sprintf('%d %s identical', count($expected), count($expected) === 1 ? 'page' : 'pages'));
    }

    /**
     * @return list<string>
     */
    private function signatures(Command $command, string $binary, string $file): array
    {
        $call = basename($binary) === 'magick' ? [$binary, 'identify'] : [$binary];
        $result = Process::timeout($command->timeout)->run(array_merge($call, ['-quiet', '-format', '%#\n', $file]));
        $signatures = array_values(array_filter(array_map('trim', explode("\n", $result->output()))));

        if (! $result->successful() || $signatures === []) {
            throw new CommandFailed("«{$command->key}» could not read the pixels of ".basename($file).': '.trim($result->errorOutput()));
        }

        return $signatures;
    }
}
