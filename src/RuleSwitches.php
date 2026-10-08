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
}
