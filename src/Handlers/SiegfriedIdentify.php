<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Handlers;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use KraenzleRitter\FormatPolicy\Command;
use KraenzleRitter\FormatPolicy\Identification;

/**
 * Siegfried on a file path: PRONOM id, MIME type, format name.
 *
 * Moved here from IdentifyMediaFormatAction so it runs before a medium
 * exists — the format policy decides on the delivered file. A missing
 * signature file is fetched once with `sf -update`, as before.
 */
final class SiegfriedIdentify implements IdentifiesFormat
{
    public function identify(Command $command, string $binary, string $file): Identification
    {
        $result = $this->run($command, $binary, $file);

        if ($this->signatureMissing($result)) {
            Log::info('Siegfried signature file missing, running sf -update');
            Process::timeout(300)->run([$binary, '-update']);

            $result = $this->run($command, $binary, $file);
            if ($this->signatureMissing($result)) {
                Log::error('Siegfried signature update failed');

                return Identification::unknown();
            }
        }

        $match = json_decode($result->output(), true)['files'][0]['matches'][0] ?? null;
        if (! is_array($match) || empty($match['id']) || $match['id'] === 'UNKNOWN') {
            return Identification::unknown();
        }

        return new Identification(
            pronomId: (string) $match['id'],
            mimeType: ! empty($match['mime']) ? (string) $match['mime'] : null,
            formatName: ! empty($match['format']) ? (string) $match['format'] : null,
        );
    }

    private function run(Command $command, string $binary, string $file): ProcessResult
    {
        return Process::timeout($command->timeout)->run([$binary, '-json', $file]);
    }

    private function signatureMissing(ProcessResult $result): bool
    {
        return str_contains($result->output().$result->errorOutput(), 'error opening signature file');
    }
}
