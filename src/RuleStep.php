<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * One command a rule calls, as the settings page shows it: in words (the
 * command's label) and, for whoever wants to know, the tool with its
 * version and the call itself — the template, or the handler class where
 * the package runs the command in PHP.
 */
final readonly class RuleStep
{
    public function __construct(
        public string $command,
        public string $kind,
        public LocalizedText $label,
        public string $tool,
        public ?LocalizedText $toolLabel,
        public ?string $version,
        public string $call,
    ) {}

    public static function of(string $commandKey, FormatPolicyRepository $policy, CommandRunner $runner): ?self
    {
        $command = $policy->command($commandKey);
        if ($command === null) {
            return null;
        }
        $tool = $policy->tool($command->tool);
        $binary = $tool?->binaries[0] ?? $command->tool;
        $handler = (string) $command->handler;

        return new self(
            command: $command->key,
            kind: $command->kind,
            label: $command->label,
            tool: $command->tool,
            toolLabel: $tool?->label,
            version: $runner->toolOf($commandKey)[1] ?? null,
            call: $command->template !== null
                ? str_replace('{binary}', $binary, $command->template)
                : substr($handler, (int) strrpos('\\'.$handler, '\\')),
        );
    }
}
