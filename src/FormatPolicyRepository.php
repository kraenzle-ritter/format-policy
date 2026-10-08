<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * The format policy's entries — the only way code reads them.
 *
 * Read from config/format-policy.php for now; a database implementation can
 * replace it without touching a caller (the fields are the columns).
 */
interface FormatPolicyRepository
{
    /**
     * @return array<string, Tool>
     */
    public function tools(): array;

    /**
     * @return array<string, Command>
     */
    public function commands(): array;

    /**
     * Rules whose commands and tools all exist, highest priority first —
     * retired ones included; whether a rule runs is the switches' question.
     *
     * @return list<Rule>
     */
    public function rules(?string $phase = null): array;

    public function tool(string $key): ?Tool;

    public function command(string $key): ?Command;

    public function rule(string $key): ?Rule;

    /**
     * What is wrong with the policy: references to entries that do not
     * exist, unknown kinds or phases, missing English descriptions.
     *
     * @return list<string>
     */
    public function problems(): array;
}
