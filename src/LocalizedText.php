<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * A short text by language — labels and descriptions of the format policy.
 *
 * Kept as a language map from the start (`['en' => …]`), the shape of
 * Anton's translatable columns (spatie/laravel-translatable), so it becomes a JSON column without change.
 * For now only English is written; any other language falls back to it.
 */
final readonly class LocalizedText
{
    /**
     * @param  array<string, string>  $texts  language => text
     */
    public function __construct(public array $texts) {}

    /**
     * @param  array<array-key, mixed>|string|null  $value  as written in the policy
     */
    public static function from(array|string|null $value): self
    {
        if (is_string($value)) {
            return new self(['en' => $value]);
        }

        return new self(array_filter((array) $value, fn ($text): bool => is_string($text) && $text !== ''));
    }

    public function in(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->texts[$locale] ?? $this->texts['en'] ?? (string) (array_values($this->texts)[0] ?? '');
    }

    public function hasEnglish(): bool
    {
        return isset($this->texts['en']);
    }
}
