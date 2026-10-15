<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * A `skip_when` entry in words — «Kompression Deflate, Predictor 2» — for
 * the settings page and for a probe's verdict.
 *
 * The facts are those of a `characterize` command. A fact without a label of
 * its own is named by its key; a list of values reads «LZW / JPEG».
 */
final class ConditionText
{
    private const COMPRESSION = [
        'deflate' => 'Deflate',
        'lzw' => 'LZW',
        'jpeg' => 'JPEG',
        'ccitt' => 'CCITT',
        'packbits' => 'PackBits',
        'jpeg2000' => 'JPEG 2000',
    ];

    private const LABELLED = ['compression', 'predictor', 'bits_per_sample', 'samples_per_pixel', 'colour_space', 'pages'];

    /**
     * @param  array<string, mixed>  $condition
     */
    public static function of(array $condition): string
    {
        $parts = [];

        foreach ($condition as $fact => $expected) {
            $fact = (string) $fact;
            $values = array_map(
                fn (mixed $value): string => match (true) {
                    $fact === 'compression' => self::compression((string) $value),
                    is_bool($value) => __('format-policy::facts.'.($value ? 'yes' : 'no')),
                    default => (string) $value,
                },
                is_array($expected) ? $expected : [$expected],
            );
            $parts[] = self::label($fact).' '.implode(' / ', $values);
        }

        return implode(', ', $parts);
    }

    public static function compression(string $key): string
    {
        return $key === 'none' ? __('format-policy::facts.uncompressed') : (self::COMPRESSION[$key] ?? $key);
    }

    public static function label(string $fact): string
    {
        return match (true) {
            $fact === 'bigtiff' => 'BigTIFF',
            in_array($fact, self::LABELLED, true) => __('format-policy::facts.'.$fact),
            default => $fact,
        };
    }
}
