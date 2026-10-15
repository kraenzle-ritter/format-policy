<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

use BackedEnum;

/**
 * When and what for commands run on a file.
 *
 * A rule matches by MIME type, PRONOM id or extension (any one suffices) and
 * by ingest route — a string the calling program defines (Anton: upload,
 * media_add, sip_ech, …; agate: its own). A characterize command may veto it (`skip_when`).
 * It runs its transform commands in order, checks the result with its verify
 * command, and says what becomes of the original. Rules are never deleted: events name
 * them. A rule is retired and, if need be, replaced by one with a new key.
 */
final readonly class Rule
{
    public const PHASES = ['preingest'];

    public const ORIGINAL = ['replace', 'keep_original'];

    /**
     * @param  list<string>  $mimeTypes
     * @param  list<string>  $pronomIds
     * @param  list<string>  $extensions
     * @param  list<string>|null  $sources  only these; null = all not excluded
     * @param  list<string>  $excludeSources
     * @param  list<array<string, mixed>>  $skipWhen  facts that, all matching, veto the rule
     * @param  list<string>  $transforms
     */
    public function __construct(
        public string $key,
        public LocalizedText $label,
        public LocalizedText $description,
        public string $phase,
        public array $mimeTypes,
        public array $pronomIds,
        public array $extensions,
        public ?array $sources,
        public array $excludeSources,
        public ?string $precheck,
        public array $skipWhen,
        public array $transforms,
        public ?string $verify,
        public bool $requireSmaller,
        public string $original,
        public int $priority,
        public bool $enabledByDefault,
        public bool $retired,
        public ?string $replaces,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function fromArray(string $key, array $entry): self
    {
        $match = (array) ($entry['match'] ?? []);
        $sources = fn (array $names): array => array_values(array_map('strval', $names));
        $precheck = (array) ($entry['precheck'] ?? []);

        return new self(
            key: $key,
            label: LocalizedText::from($entry['label'] ?? null),
            description: LocalizedText::from($entry['description'] ?? null),
            phase: (string) ($entry['phase'] ?? ''),
            mimeTypes: array_values(array_map('strtolower', (array) ($match['mime_types'] ?? []))),
            pronomIds: array_values(array_map('strval', (array) ($match['pronom_ids'] ?? []))),
            extensions: array_values(array_map('strtolower', (array) ($match['extensions'] ?? []))),
            sources: isset($entry['sources']) ? $sources((array) $entry['sources']) : null,
            excludeSources: $sources((array) ($entry['exclude_sources'] ?? [])),
            precheck: isset($precheck['command']) ? (string) $precheck['command'] : null,
            skipWhen: array_values((array) ($precheck['skip_when'] ?? [])),
            transforms: array_values(array_map('strval', (array) ($entry['transform'] ?? []))),
            verify: isset($entry['verify']) ? (string) $entry['verify'] : null,
            requireSmaller: (bool) ($entry['require_smaller'] ?? false),
            original: (string) ($entry['original'] ?? 'replace'),
            priority: (int) ($entry['priority'] ?? 100),
            enabledByDefault: (bool) ($entry['enabled_by_default'] ?? false),
            retired: (bool) ($entry['retired'] ?? false),
            replaces: isset($entry['replaces']) ? (string) $entry['replaces'] : null,
        );
    }

    /**
     * The commands this rule calls, in the order it calls them.
     *
     * @return list<string>
     */
    public function commandKeys(): array
    {
        return array_values(array_filter([$this->precheck, ...$this->transforms, $this->verify]));
    }

    public function admits(string|BackedEnum $source): bool
    {
        $source = self::sourceKey($source);

        if (in_array($source, $this->excludeSources, true)) {
            return false;
        }

        return $this->sources === null || in_array($source, $this->sources, true);
    }

    public function matchesFormat(?string $mimeType, ?string $pronomId, string $extension): bool
    {
        return ($mimeType !== null && in_array(strtolower($mimeType), $this->mimeTypes, true))
            || ($pronomId !== null && in_array($pronomId, $this->pronomIds, true))
            || in_array(strtolower($extension), $this->extensions, true);
    }

    /**
     * An ingest route as the rules name it.
     */
    public static function sourceKey(string|BackedEnum $source): string
    {
        return $source instanceof BackedEnum ? (string) $source->value : $source;
    }
}
