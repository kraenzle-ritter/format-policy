<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Preingest;

use KraenzleRitter\FormatPolicy\Identification;

/**
 * What the preingest would do to one file, found without converting it:
 * how the file was identified, what the prechecks measured, and a verdict
 * for every rule in force on the route.
 *
 * Each rule is judged against the file as delivered. Where two rules would
 * act one after the other, the second one would in the run see the result
 * of the first; the probe cannot know that result without converting.
 */
final readonly class Probe
{
    /**
     * @param  array<string, array{command: string, tool: string|null, version: string|null, facts: array<string, scalar|null>}>  $characteristics  by command
     * @param  list<RuleVerdict>  $verdicts
     */
    public function __construct(
        public string $path,
        public int|false $size,
        public Identification $identification,
        public array $characteristics,
        public array $verdicts,
    ) {}

    /**
     * @return list<RuleVerdict>
     */
    public function applying(): array
    {
        return array_values(array_filter($this->verdicts, fn (RuleVerdict $verdict): bool => $verdict->applies()));
    }
}
