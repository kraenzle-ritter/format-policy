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
 *
 * What the rule does, as «if … then …»: it acts on a file of one of these
 * formats (`mimeTypes`, `pronomIds`, `extensions` — any one suffices), except
 * where its `precheck` finds the facts of a `skipWhen` entry; then it runs
 * `steps` in order, checks the result with `verify`, and replaces or keeps
 * the original. `position` is its place in the order rules run in.
 */
final readonly class RuleOverviewEntry
{
    /**
     * @param  list<string>|null  $sources  only these routes; null = all not excluded
     * @param  list<string>  $excludeSources
     * @param  list<string>  $missingTools
     * @param  list<string>  $mimeTypes
     * @param  list<string>  $pronomIds
     * @param  list<string>  $extensions
     * @param  list<array<string, mixed>>  $skipWhen
     * @param  list<RuleStep>  $steps
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
        public int $position = 0,
        public array $mimeTypes = [],
        public array $pronomIds = [],
        public array $extensions = [],
        public ?RuleStep $precheck = null,
        public array $skipWhen = [],
        public array $steps = [],
        public ?RuleStep $verify = null,
        public string $original = 'replace',
        public bool $requireSmaller = false,
    ) {}

    public function keepsOriginal(): bool
    {
        return $this->original === 'keep_original';
    }

    public function runsHere(): bool
    {
        return $this->missingTools === [];
    }
}
