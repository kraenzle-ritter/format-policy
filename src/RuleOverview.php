<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

use BackedEnum;

/**
 * The rules of the policy as an archive sees them on its settings page
 * (anton#655): what each one does, on which routes, whether it is in force
 * for the tenant and whether this server can run it.
 *
 * Retired rules are left out; they cannot be switched on. Tools and commands
 * stay inside the package — the page speaks of rules only.
 */
final readonly class RuleOverview
{
    /**
     * @param  list<RuleOverviewEntry>  $entries
     */
    public function __construct(public array $entries) {}

    /**
     * @param  string|BackedEnum|null  $source  the ingest route the caller asks about; null = all
     */
    public static function for(FormatPolicyRepository $policy, SwitchStore $store, CommandRunner $runner, string|BackedEnum|null $source = null): self
    {
        $switches = new RuleSwitches($policy, $store);
        $entries = [];

        foreach ($policy->rules() as $rule) {
            if ($rule->retired) {
                continue;
            }
            $entries[] = new RuleOverviewEntry(
                key: $rule->key,
                label: $rule->label,
                description: $rule->description,
                phase: $rule->phase,
                enabledByDefault: $rule->enabledByDefault,
                override: $switches->override($rule),
                inForce: $switches->isInForce($rule),
                admits: $source === null ? null : $rule->admits($source),
                sources: $rule->sources,
                excludeSources: $rule->excludeSources,
                missingTools: $runner->missingTools($rule),
            );
        }

        return new self($entries);
    }

    /**
     * The entries for one route; all of them when none is given.
     *
     * @return list<RuleOverviewEntry>
     */
    public function admitting(): array
    {
        return array_values(array_filter($this->entries, fn (RuleOverviewEntry $entry): bool => $entry->admits !== false));
    }
}
