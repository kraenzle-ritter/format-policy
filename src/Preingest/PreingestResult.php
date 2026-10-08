<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Preingest;

use Illuminate\Support\Facades\File;
use KraenzleRitter\FormatPolicy\Identification;

/**
 * The outcome of the preingest for one file.
 *
 * `path` is the file to ingest — the delivered one, or a result in the
 * working directory. The working directory lives until cleanup(); the
 * caller ingests first, then cleans up.
 */
final class PreingestResult
{
    /**
     * @param  list<RuleOutcome>  $outcomes
     * @param  array{command: string, tool: string|null, version: string|null, facts: array<string, scalar|null>}|null  $characteristics  what a precheck found about `path`
     */
    public function __construct(
        public readonly string $path,
        public readonly string $deliveredPath,
        public readonly array $outcomes,
        public readonly ?Identification $identification,
        public readonly bool $keepOriginal,
        public readonly ?string $workDir,
        public readonly ?array $characteristics = null,
    ) {}

    public static function untouched(string $path): self
    {
        return new self($path, $path, [], null, false, null);
    }

    public function changed(): bool
    {
        return $this->path !== $this->deliveredPath;
    }

    public function cleanup(): void
    {
        if ($this->workDir !== null && is_dir($this->workDir)) {
            File::deleteDirectory($this->workDir);
        }
    }
}
