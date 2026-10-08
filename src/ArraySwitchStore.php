<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * Rule switches held in memory — from a config array, a snapshot fetched
 * elsewhere, or a test.
 */
final class ArraySwitchStore implements SwitchStore
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public function __construct(private array $overrides = []) {}

    public function overrides(): array
    {
        return array_map(fn (mixed $on): bool => (bool) $on, $this->overrides);
    }

    public function set(string $ruleKey, bool $on): void
    {
        $this->overrides[$ruleKey] = $on;
    }
}
