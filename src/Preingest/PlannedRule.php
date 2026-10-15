<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Preingest;

/**
 * A rule that would act on a file — for showing what will happen after an
 * upload or a validation, before it does.
 */
final readonly class PlannedRule
{
    public function __construct(
        public string $rule,
        public string $label,
        public string $description,
    ) {}
}
