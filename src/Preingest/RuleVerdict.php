<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Preingest;

/**
 * Whether a rule in force would act on a file, and if not, why not.
 *
 * `condition` is the `skip_when` entry whose facts all matched, for
 * PRECHECK; `missingTools` the tools this server lacks, for TOOLS_MISSING.
 */
final readonly class RuleVerdict
{
    /** The rule would act on the file. */
    public const APPLIES = 'applies';

    /** Neither MIME type, PRONOM id nor extension match the rule. */
    public const OTHER_FORMAT = 'other_format';

    /** The precheck found facts that veto the rule. */
    public const PRECHECK = 'precheck';

    /** The precheck could not be run on the file; the rule would not act. */
    public const PRECHECK_FAILED = 'precheck_failed';

    /** A tool the rule needs is not installed on this server. */
    public const TOOLS_MISSING = 'tools_missing';

    /**
     * @param  array<string, mixed>  $condition
     * @param  list<string>  $missingTools
     */
    public function __construct(
        public string $rule,
        public string $label,
        public string $description,
        public string $verdict,
        public array $condition = [],
        public array $missingTools = [],
    ) {}

    public function applies(): bool
    {
        return $this->verdict === self::APPLIES;
    }
}
