<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

/**
 * What an identify command says about a file.
 */
final readonly class Identification
{
    public function __construct(
        public ?string $pronomId,
        public ?string $mimeType,
        public ?string $formatName,
    ) {}

    public static function unknown(): self
    {
        return new self(null, null, null);
    }

    public function isKnown(): bool
    {
        return $this->pronomId !== null;
    }
}
