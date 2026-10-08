<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

use Illuminate\Support\Facades\Process;
use KraenzleRitter\FormatPolicy\Handlers\CharacterizesFile;
use KraenzleRitter\FormatPolicy\Handlers\IdentifiesFormat;
use KraenzleRitter\FormatPolicy\Handlers\TransformsFile;
use KraenzleRitter\FormatPolicy\Handlers\VerifiesResult;

/**
 * Runs the commands of the format policy by key.
 *
 * Finds the tool's program on this server, then calls the command's handler
 * or fills its template. Everything that goes wrong is a CommandFailed: the
 * caller decides what that means (for the preingest: ingest the original).
 */
final class CommandRunner
{
    public function __construct(
        private readonly FormatPolicyRepository $policy,
        private readonly ToolLocator $tools,
    ) {}

    public function identify(string $key, string $file): Identification
    {
        [$command, $binary, $handler] = $this->resolve($key, 'identify');

        if (! $handler instanceof IdentifiesFormat) {
            throw new CommandFailed("identify command «{$key}» needs a handler");
        }

        return $handler->identify($command, $binary, $file);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function characterize(string $key, string $file): array
    {
        [$command, $binary, $handler] = $this->resolve($key, 'characterize');

        if (! $handler instanceof CharacterizesFile) {
            throw new CommandFailed("characterize command «{$key}» needs a handler");
        }

        return $handler->characterize($command, $binary, $file);
    }

    /**
     * Runs a transform step; returns the file it wrote.
     */
    public function transform(string $key, string $original, string $source, string $target): string
    {
        [$command, $binary, $handler] = $this->resolve($key, 'transform');
        $target = $command->inPlace ? $source : $target;

        if ($handler instanceof TransformsFile) {
            $handler->transform($command, $binary, $original, $source, $target);
        } else {
            $this->runTemplate($command, $binary, ['{source}' => $source, '{target}' => $target, '{original}' => $original]);
        }

        if (! is_file($target) || filesize($target) === 0) {
            throw new CommandFailed("transform «{$key}» wrote no file");
        }

        return $target;
    }

    public function verify(string $key, string $original, string $result): Verification
    {
        [$command, $binary, $handler] = $this->resolve($key, 'verify');

        if (! $handler instanceof VerifiesResult) {
            throw new CommandFailed("verify command «{$key}» needs a handler");
        }

        return $handler->verify($command, $binary, $original, $result);
    }

    /**
     * The tools a rule needs that this server does not have.
     *
     * @return list<string>
     */
    public function missingTools(Rule $rule): array
    {
        $missing = [];
        foreach ($rule->commandKeys() as $commandKey) {
            $toolKey = (string) $this->policy->command($commandKey)?->tool;
            $tool = $this->policy->tool($toolKey);
            if ($tool === null || ! $this->tools->isAvailable($tool)) {
                $missing[] = $toolKey;
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * The tool and its version behind a command — for the event.
     *
     * @return array{string, string|null}|null
     */
    public function toolOf(string $key): ?array
    {
        $tool = $this->policy->tool((string) $this->policy->command($key)?->tool);

        return $tool === null ? null : [$tool->key, $this->tools->version($tool)];
    }

    /**
     * @return array{Command, string, object|null}
     */
    private function resolve(string $key, string $kind): array
    {
        $command = $this->policy->command($key);
        if ($command === null || $command->kind !== $kind) {
            throw new CommandFailed("there is no {$kind} command «{$key}»");
        }

        $tool = $this->policy->tool($command->tool);
        $binary = $tool === null ? null : $this->tools->binary($tool);
        if ($binary === null) {
            throw new CommandFailed("the tool «{$command->tool}» of «{$key}» is not installed");
        }

        return [$command, $binary, $command->handler !== null ? app($command->handler) : null];
    }

    /**
     * @param  array<string, string>  $files  placeholder => path
     */
    private function runTemplate(Command $command, string $binary, array $files): void
    {
        $line = str_replace('{binary}', escapeshellcmd($binary), (string) $command->template);
        foreach ($files as $placeholder => $path) {
            $line = str_replace($placeholder, escapeshellarg($path), $line);
        }

        $result = Process::timeout($command->timeout)->run($line);
        if (! $result->successful()) {
            throw new CommandFailed(sprintf('«%s» ended with %d: %s', $command->key, (int) $result->exitCode(), trim($result->errorOutput())));
        }
    }
}
