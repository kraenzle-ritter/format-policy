<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * How a tool is called — independent of what for.
 *
 * The kind says what a call yields: `transform` writes a file, `verify`
 * compares the file before and after, `identify` names the format,
 * `characterize` reports properties a rule can decide on. A command is a
 * template (`{binary} {source} {target}`) or a handler class for anything that
 * needs more than one call or reads its output. Rules refer to commands by
 * key, so one command serves many rules.
 */
final readonly class Command
{
    public const KINDS = ['transform', 'verify', 'identify', 'characterize'];

    /**
     * @param  class-string|null  $handler
     */
    public function __construct(
        public string $key,
        public LocalizedText $label,
        public LocalizedText $description,
        public string $kind,
        public string $tool,
        public ?string $template,
        public ?string $handler,
        public bool $inPlace,
        public int $timeout,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function fromArray(string $key, array $entry): self
    {
        return new self(
            key: $key,
            label: LocalizedText::from($entry['label'] ?? null),
            description: LocalizedText::from($entry['description'] ?? null),
            kind: (string) ($entry['kind'] ?? ''),
            tool: (string) ($entry['tool'] ?? ''),
            template: isset($entry['template']) ? (string) $entry['template'] : null,
            handler: isset($entry['handler']) ? (string) $entry['handler'] : null,
            inPlace: (bool) ($entry['in_place'] ?? false),
            timeout: (int) ($entry['timeout'] ?? 120),
        );
    }
}
