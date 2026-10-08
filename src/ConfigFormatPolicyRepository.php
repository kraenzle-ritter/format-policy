<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * The format policy: the package's catalogue and the entries a program adds
 * in config/format-policy.php.
 *
 * An added entry under a key the catalogue already has is reported in
 * problems() and ignored — a key means the same in every program.
 *
 * A rule that names a command which does not exist, or a command whose tool
 * does not, is left out of rules() and reported in problems() — logged once,
 * never run. One broken entry must not take the others down, and it must not
 * run half.
 */
final class ConfigFormatPolicyRepository implements FormatPolicyRepository
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /** @var array<string, Command> */
    private array $commands = [];

    /** @var array<string, Rule> */
    private array $rules = [];

    /** @var list<string> */
    private array $problems = [];

    public const CATALOGUE = __DIR__.'/../resources/catalogue.php';

    private const SECTIONS = ['tools' => 'tool', 'commands' => 'command', 'rules' => 'rule'];

    /**
     * @param  array<string, mixed>|null  $policy  a complete policy; null reads the catalogue and the program's additions
     */
    public function __construct(?array $policy = null)
    {
        if ($policy === null) {
            [$policy, $this->problems] = self::merge(require self::CATALOGUE, (array) config('format-policy'));
        }

        foreach ((array) ($policy['tools'] ?? []) as $key => $entry) {
            $this->tools[(string) $key] = Tool::fromArray((string) $key, (array) $entry);
        }
        foreach ((array) ($policy['commands'] ?? []) as $key => $entry) {
            $this->commands[(string) $key] = Command::fromArray((string) $key, (array) $entry);
        }
        foreach ((array) ($policy['rules'] ?? []) as $key => $entry) {
            $this->rules[(string) $key] = Rule::fromArray((string) $key, (array) $entry);
        }

        $this->check();

        foreach ($this->problems as $problem) {
            logger()->error('Format policy: '.$problem);
        }
    }

    public function tools(): array
    {
        return $this->tools;
    }

    public function commands(): array
    {
        return $this->commands;
    }

    public function rules(?string $phase = null): array
    {
        $rules = array_filter(
            $this->rules,
            fn (Rule $rule): bool => ($phase === null || $rule->phase === $phase) && $this->isComplete($rule),
        );
        usort($rules, fn (Rule $a, Rule $b): int => [$b->priority, $a->key] <=> [$a->priority, $b->key]);

        return $rules;
    }

    public function tool(string $key): ?Tool
    {
        return $this->tools[$key] ?? null;
    }

    public function command(string $key): ?Command
    {
        return $this->commands[$key] ?? null;
    }

    public function rule(string $key): ?Rule
    {
        return $this->rules[$key] ?? null;
    }

    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * The catalogue with a program's additions; an addition under a key the
     * catalogue has is left out and named.
     *
     * @param  array<string, mixed>  $catalogue
     * @param  array<string, mixed>  $additions
     * @return array{array<string, mixed>, list<string>}
     */
    public static function merge(array $catalogue, array $additions): array
    {
        $problems = [];
        foreach (self::SECTIONS as $section => $kind) {
            foreach ((array) ($additions[$section] ?? []) as $key => $entry) {
                if (isset($catalogue[$section][$key])) {
                    $problems[] = "the {$kind} «{$key}» is in the catalogue; a program may add entries, not redefine them";

                    continue;
                }
                $catalogue[$section][$key] = $entry;
            }
        }

        return [$catalogue, $problems];
    }

    private function isComplete(Rule $rule): bool
    {
        foreach ($rule->commandKeys() as $commandKey) {
            $command = $this->commands[$commandKey] ?? null;
            if ($command === null || ! isset($this->tools[$command->tool])) {
                return false;
            }
        }

        return true;
    }

    private function check(): void
    {
        foreach ([...$this->tools, ...$this->commands, ...$this->rules] as $entry) {
            if (! $entry->description->hasEnglish() || ! $entry->label->hasEnglish()) {
                $this->problems[] = sprintf('%s «%s» has no English label or description', class_basename($entry), $entry->key);
            }
        }

        foreach ($this->commands as $command) {
            if (! in_array($command->kind, Command::KINDS, true)) {
                $this->problems[] = "command «{$command->key}» has the unknown kind «{$command->kind}»";
            }
            if (! isset($this->tools[$command->tool])) {
                $this->problems[] = "command «{$command->key}» names the tool «{$command->tool}», which does not exist";
            }
            if ($command->template === null && $command->handler === null) {
                $this->problems[] = "command «{$command->key}» has neither a template nor a handler";
            }
        }

        foreach ($this->rules as $rule) {
            if (! in_array($rule->phase, Rule::PHASES, true)) {
                $this->problems[] = "rule «{$rule->key}» has the unknown phase «{$rule->phase}»";
            }
            if (! in_array($rule->original, Rule::ORIGINAL, true)) {
                $this->problems[] = "rule «{$rule->key}» says «{$rule->original}» about the original";
            }
            foreach ($rule->commandKeys() as $commandKey) {
                if (! isset($this->commands[$commandKey])) {
                    $this->problems[] = "rule «{$rule->key}» names the command «{$commandKey}», which does not exist";
                }
            }
            if ($rule->replaces !== null && ! isset($this->rules[$rule->replaces])) {
                $this->problems[] = "rule «{$rule->key}» replaces «{$rule->replaces}», which does not exist";
            }
        }
    }
}
