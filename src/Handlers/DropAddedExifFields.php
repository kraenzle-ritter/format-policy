<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Handlers;

use Illuminate\Support\Facades\Process;
use KraenzleRitter\FormatPolicy\Command;
use KraenzleRitter\FormatPolicy\CommandFailed;

/**
 * Removes the fields ExifTool adds on its own when it writes an EXIF or IPTC
 * block, where the delivered file did not have them — so the converted file
 * carries the delivered metadata and nothing invented.
 */
final class DropAddedExifFields implements TransformsFile
{
    /**
     * Fields ExifTool treats as mandatory and fills in.
     *
     * @var list<string>
     */
    public const FIELDS = [
        'ExifIFD:ColorSpace',
        'ExifIFD:ComponentsConfiguration',
        'ExifIFD:FlashpixVersion',
        'IPTC:EnvelopeRecordVersion',
    ];

    public function transform(Command $command, string $binary, string $original, string $source, string $target): void
    {
        $added = array_values(array_diff($this->present($command, $binary, $source), $this->present($command, $binary, $original)));
        if ($added === []) {
            return;
        }

        $result = Process::timeout($command->timeout)->run(array_merge(
            [$binary, '-q', '-q', '-m', '-overwrite_original'],
            array_map(fn (string $field): string => '-'.$field.'=', $added),
            [$target],
        ));

        if (! $result->successful()) {
            throw new CommandFailed("«{$command->key}» ended with {$result->exitCode()}: ".trim($result->errorOutput()));
        }
    }

    /**
     * Which of the fields a file has.
     *
     * @return list<string>
     */
    private function present(Command $command, string $binary, string $file): array
    {
        $result = Process::timeout($command->timeout)->run(array_merge(
            [$binary, '-j', '-G1', '-n'],
            array_map(fn (string $field): string => '-'.$field, self::FIELDS),
            [$file],
        ));
        $tags = json_decode($result->output(), true)[0] ?? null;

        if (! $result->successful() || ! is_array($tags)) {
            throw new CommandFailed("«{$command->key}» could not read ".basename($file).': '.trim($result->errorOutput()));
        }

        return array_values(array_intersect(self::FIELDS, array_keys($tags)));
    }
}
