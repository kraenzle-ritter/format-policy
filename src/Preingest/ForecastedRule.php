<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Preingest;

/**
 * A rule in force that would at most act on `count` of `total` files.
 */
final readonly class ForecastedRule
{
    public function __construct(
        public string $rule,
        public string $label,
        public string $description,
        public int $count,
        public int $total,
    ) {}
}
