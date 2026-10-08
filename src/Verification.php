<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * What a verify command found when comparing original and result.
 */
final readonly class Verification
{
    public function __construct(
        public bool $passed,
        public string $detail,
    ) {}
}
