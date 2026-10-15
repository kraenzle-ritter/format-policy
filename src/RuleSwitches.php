<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * Which rules are in force for a tenant.
 *
 * A rule's default (`enabled_by_default`) is overridden, either way, by the
 * tenant's value in its SwitchStore. A retired rule is never in force,
 * whatever the store says.
 */
final class RuleSwitches
{
    public function __construct(
        private readonly FormatPolicyRepository $policy,
        private readonly SwitchStore $store,
    ) {}

    public function isInForce(Rule $rule): bool
    {
        if ($rule->retired) {
            return false;
        }

        return $this->override($rule) ?? $rule->enabledByDefault;
    }

    /**
     * The tenant's own value for a rule; null where it follows the default.
     */
    public function override(Rule $rule): ?bool
    {
        return $this->store->overrides()[$rule->key] ?? null;
    }

    /**
     * @return list<Rule>
     */
    public function inForce(string $phase): array
    {
        return array_values(array_filter($this->policy->rules($phase), fn (Rule $rule): bool => $this->isInForce($rule)));
    }

    public function set(Rule $rule, bool $on): void
    {
        $this->store->set($rule->key, $on);
    }

    /**
     * Sets the switches a settings form sent (`rules[<key>]`, as the view
     * `format-policy::rules` writes it) and returns what changed — for the
     * calling program's log.
     *
     * Only what changes is written: a rule whose value already holds keeps
     * following its default. Keys the policy does not know, and retired
     * rules, are ignored.
     *
     * @param  array<array-key, mixed>  $input
     * @return list<array{rule: string, from: bool, to: bool}>
     */
    public function apply(array $input): array
    {
        $changes = [];

        foreach ($this->policy->rules() as $rule) {
            if ($rule->retired || ! array_key_exists($rule->key, $input)) {
                continue;
            }
            $on = filter_var($input[$rule->key], FILTER_VALIDATE_BOOLEAN);
            $was = $this->isInForce($rule);
            if ($on !== $was) {
                $this->set($rule, $on);
                $changes[] = ['rule' => $rule->key, 'from' => $was, 'to' => $on];
            }
        }

        return $changes;
    }
}
