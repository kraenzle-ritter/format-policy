<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * A program the format policy calls (`tiffcp`, `exiftool`, `magick`, `sf`).
 *
 * `binaries` are tried in order — the first one on PATH is the tool on this
 * server (ImageMagick 7 is `magick`, 6 is `identify`/`convert`). The version
 * command and pattern give the version that goes into every event the tool
 * takes part in.
 */
final readonly class Tool
{
    /**
     * @param  list<string>  $binaries
     */
    public function __construct(
        public string $key,
        public LocalizedText $label,
        public LocalizedText $description,
        public array $binaries,
        public string $versionCommand,
        public string $versionPattern,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function fromArray(string $key, array $entry): self
    {
        return new self(
            key: $key,
            label: LocalizedText::from($entry['label'] ?? null),
            description: LocalizedText::from($entry['description'] ?? null),
            binaries: array_values(array_map('strval', (array) ($entry['binaries'] ?? []))),
            versionCommand: (string) ($entry['version_command'] ?? '{binary} --version'),
            versionPattern: (string) ($entry['version_pattern'] ?? '/(\d+(?:\.\d+)+)/'),
        );
    }
}
