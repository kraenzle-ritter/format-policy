<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

use Illuminate\Support\Facades\Process;

/**
 * Where a tool's program is on this server, and which version it is.
 *
 * Resolved against PATH with is_executable() rather than by forking `which`.
 * Both answers are kept for the process: a version does not change while
 * the program runs.
 */
final class ToolLocator
{
    /** @var array<string, string|null> */
    private array $binaries = [];

    /** @var array<string, string|null> */
    private array $versions = [];

    /**
     * The first of the tool's programs found on PATH, or null.
     */
    public function binary(Tool $tool): ?string
    {
        if (! array_key_exists($tool->key, $this->binaries)) {
            $this->binaries[$tool->key] = null;
            foreach ($tool->binaries as $binary) {
                if ($this->onPath($binary)) {
                    $this->binaries[$tool->key] = $binary;
                    break;
                }
            }
        }

        return $this->binaries[$tool->key];
    }

    public function isAvailable(Tool $tool): bool
    {
        return $this->binary($tool) !== null;
    }

    public function version(Tool $tool): ?string
    {
        if (! array_key_exists($tool->key, $this->versions)) {
            $binary = $this->binary($tool);
            $this->versions[$tool->key] = null;
            if ($binary !== null) {
                // `|| true`: some programs answer their version with a non-zero
                // exit (tiffcp without arguments); that is no failure to log.
                $output = Process::timeout(10)->run(str_replace('{binary}', $binary, $tool->versionCommand).' 2>&1 || true');
                if (preg_match($tool->versionPattern, $output->output().$output->errorOutput(), $match) === 1) {
                    $this->versions[$tool->key] = $match[1];
                }
            }
        }

        return $this->versions[$tool->key];
    }

    private function onPath(string $binary): bool
    {
        if (str_contains($binary, '/')) {
            return is_executable($binary);
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if ($dir !== '' && is_executable(rtrim($dir, '/').'/'.$binary)) {
                return true;
            }
        }

        return false;
    }
}
