<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

use BackedEnum;
use KraenzleRitter\FormatPolicy\Preingest\ForecastedRule;

/**
 * What the preingest would at most do to a list of files, before an import.
 *
 * Counted by extension and MIME type (finfo) only: no Siegfried, no precheck,
 * no tool at all — an import may name thousands of files. The count is
 * therefore an upper bound; whether a file needs the rule is decided when it
 * is ingested.
 */
final class PreingestForecast
{
    public function __construct(private readonly Preingest $preingest) {}

    /**
     * @param  list<string>  $files
     * @return list<ForecastedRule>
     */
    public function forFiles(array $files, string|BackedEnum $source): array
    {
        $rules = $this->preingest->rulesInForce($source);
        if ($rules === [] || $files === []) {
            return [];
        }

        $formats = array_map(fn (string $file): array => [
            is_file($file) ? (mime_content_type($file) ?: null) : null,
            pathinfo($file, PATHINFO_EXTENSION),
        ], $files);

        $forecast = [];
        foreach ($rules as $rule) {
            $count = count(array_filter($formats, fn (array $format): bool => $rule->matchesFormat($format[0], null, $format[1])));
            if ($count > 0) {
                $forecast[] = new ForecastedRule($rule->key, $rule->label->in(), $rule->description->in(), $rule->optional, $count, count($files));
            }
        }

        return $forecast;
    }
}
