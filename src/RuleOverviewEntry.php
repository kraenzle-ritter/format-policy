<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * One rule in the RuleOverview.
 *
 * `override` is the tenant's own value, null where it follows
 * `enabledByDefault`; `inForce` is what holds. `admits` answers for the route
 * the overview was asked about (null when it was asked about none).
 * `missingTools` empty means this server can run the rule.
 */
final readonly class RuleOverviewEntry
{
    /**
     * @param  list<string>|null  $sources  only these routes; null = all not excluded
     * @param  list<string>  $excludeSources
     * @param  list<string>  $missingTools
     */
    public function __construct(
        public string $key,
        public LocalizedText $label,
        public LocalizedText $description,
        public string $phase,
        public bool $enabledByDefault,
        public ?bool $override,
        public bool $inForce,
        public ?bool $admits,
        public ?array $sources,
        public array $excludeSources,
        public array $missingTools,
    ) {}

    public function runsHere(): bool
    {
        return $this->missingTools === [];
    }
}
