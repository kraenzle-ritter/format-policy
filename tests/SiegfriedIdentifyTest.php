<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Tests;

use KraenzleRitter\FormatPolicy\CommandRunner;

/**
 * The `siegfried` identify command works on a file path, before any medium
 * exists — the format policy decides on the delivered file.
 */
final class SiegfriedIdentifyTest extends TestCase
{
    public function test_a_tiff_is_named_by_pronom_and_mime(): void
    {
        $identification = app(CommandRunner::class)->identify('siegfried', __DIR__.'/fixtures/rgb_no_profile.tif');

        $this->assertSame('fmt/353', $identification->pronomId);
        $this->assertSame('image/tiff', $identification->mimeType);
    }

    public function test_a_pdf_is_named_by_pronom_and_mime(): void
    {
        $identification = app(CommandRunner::class)->identify('siegfried', __DIR__.'/fixtures/blank.pdf');

        $this->assertStringStartsWith('fmt/', (string) $identification->pronomId);
        $this->assertSame('application/pdf', $identification->mimeType);
    }

    public function test_a_file_siegfried_does_not_know_is_unknown(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'qj');
        file_put_contents($file, random_bytes(64));

        try {
            $this->assertFalse(app(CommandRunner::class)->identify('siegfried', $file)->isKnown());
        } finally {
            @unlink($file);
        }
    }
}
