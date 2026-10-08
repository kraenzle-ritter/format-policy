<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Preingest;

/**
 * What one rule did to a file — becomes a `format-normalization` event.
 *
 * `ok`: converted and verified; `failed`: a step or the verification failed,
 * the original was ingested; `info`: verified but not taken (not smaller).
 */
final readonly class RuleOutcome
{
    /** Stable values: programs store them as an event's status (Anton: MediumEvent). */
    public const OK = 'ok';

    public const FAILED = 'failed';

    public const INFO = 'info';

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $rule,
        public string $status,
        public array $details,
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public static function ok(string $rule, array $details): self
    {
        return new self($rule, self::OK, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function failed(string $rule, array $details): self
    {
        return new self($rule, self::FAILED, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function info(string $rule, array $details): self
    {
        return new self($rule, self::INFO, $details);
    }
}
