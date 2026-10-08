<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * Where a tenant's rule switches are kept.
 *
 * The policy says what a rule is; the store only says, per rule key, whether
 * a tenant overrides its default. A program with one tenant per process
 * binds one store in the container; a program
 * serving several tenants in one process hands the preingest one store per
 * tenant (Preingest::withSwitches()).
 */
interface SwitchStore
{
    /**
     * The tenant's own values, by rule key; a key that is missing follows
     * the rule's default.
     *
     * @return array<string, bool>
     */
    public function overrides(): array;

    public function set(string $ruleKey, bool $on): void;
}
