<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Tests;

use Illuminate\Support\Facades\Process;
use KraenzleRitter\FormatPolicy\CommandRunner;
use KraenzleRitter\FormatPolicy\ConfigFormatPolicyRepository;
use KraenzleRitter\FormatPolicy\FormatPolicyRepository;
use KraenzleRitter\FormatPolicy\Preingest;
use KraenzleRitter\FormatPolicy\Preingest\PreingestResult;
use KraenzleRitter\FormatPolicy\Preingest\RuleOutcome;
use KraenzleRitter\FormatPolicy\Preingest\RuleVerdict;
use KraenzleRitter\FormatPolicy\RuleSwitches;

/**
 * The rule tiff_zip with the real tools: tiffcp, exiftool,
 * ImageMagick. The fixtures are built by
 * `tests/fixtures/make-fixtures.sh`.
 */
final class TiffZipTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    /** @var list<PreingestResult> */
    private array $results = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(FormatPolicyRepository::class, $policy = new ConfigFormatPolicyRepository);
        app(RuleSwitches::class)->set($policy->rule('tiff_zip'), true);
    }

    protected function tearDown(): void
    {
        foreach ($this->results as $result) {
            $result->cleanup();
        }
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_an_uncompressed_tiff_becomes_deflate_with_predictor_2_and_keeps_its_metadata(): void
    {
        $result = $this->preingest('uncompressed.tif');

        $this->assertOk($result);
        $this->assertSame(['compression' => 'deflate', 'predictor' => 2], $this->packing($result->path));
        $this->assertSame($this->metadata($result->deliveredPath), $this->metadata($result->path));
        $this->assertSame('QJ Test RGB D65', trim((string) ($this->metadata($result->path)['ICC_Profile:ProfileDescription'] ?? '')));
        $this->assertSame([], $this->tags($result->path, array_map(fn (string $field): string => '-'.$field, ['ExifIFD:ColorSpace', 'ExifIFD:ComponentsConfiguration', 'ExifIFD:FlashpixVersion'])), 'no field the delivery lacked');

        $details = $result->outcomes[0]->details;
        $this->assertSame(['exiftool', 'imagemagick', 'tiffcp'], $this->sorted(array_keys($details['tools'])));
        $this->assertNotNull($details['tools']['tiffcp']);
        $this->assertSame('fmt/353', $details['before']['pronom']);
        $this->assertSame('fmt/353', $details['after']['pronom']);
        $this->assertLessThan($details['before']['size'], $details['after']['size']);
        $this->assertTrue($details['verification']['passed']);
    }

    public function test_lzw_without_predictor_is_converted_with_its_metadata(): void
    {
        $result = $this->preingest('lzw.tif');

        $this->assertOk($result);
        $this->assertSame(['compression' => 'deflate', 'predictor' => 2], $this->packing($result->path));
        $metadata = $this->metadata($result->path);
        $this->assertSame($this->metadata($result->deliveredPath), $metadata);
        $this->assertSame('QJ Fotograf', $metadata['IFD0:Artist'] ?? null);
        $this->assertSame('2020:01:02 03:04:05', $metadata['ExifIFD:DateTimeOriginal'] ?? null);
        $this->assertSame('QJ Titel', $metadata['XMP-dc:Title'] ?? null);
        $this->assertSame('QJ Objekt', $metadata['IPTC:ObjectName'] ?? null);
    }

    /**
     * exiftool writes its own name into XMP-x:XMPToolkit whenever it writes
     * XMP; the copy takes the delivered value back.
     */
    public function test_the_xmp_toolkit_of_the_delivery_is_kept(): void
    {
        $file = $this->fixture('lzw.tif');
        Process::run(['exiftool', '-q', '-overwrite_original', '-XMP-x:XMPToolkit=Adobe XMP Core 5.6-c140', $file])->throw();

        $result = $this->results[] = app(Preingest::class)->run($file, 'media_add');

        $this->assertOk($result);
        $this->assertSame('Adobe XMP Core 5.6-c140', $this->metadata($result->path)['XMP-x:XMPToolkit'] ?? null);
    }

    public function test_another_icc_profile_is_kept(): void
    {
        $result = $this->preingest('icc_romm.tif');

        $this->assertOk($result);
        $this->assertSame('QJ Test RGB Wide D50', trim((string) ($this->metadata($result->path)['ICC_Profile:ProfileDescription'] ?? '')));
    }

    public function test_a_preview_in_ifd1_stays_a_preview(): void
    {
        $result = $this->preingest('preview_ifd1.tif');

        $this->assertOk($result);
        $this->assertSame([1], array_values($this->tags($result->path, ['-IFD1:SubfileType'])));
        $this->assertSame('2 pages identical', $result->outcomes[0]->details['verification']['detail']);
    }

    public function test_sixteen_bit_grayscale_cmyk_and_several_pages_are_converted_pixel_for_pixel(): void
    {
        foreach (['rgb16.tif' => 1, 'gray.tif' => 1, 'cmyk.tif' => 1, 'multipage.tif' => 3] as $fixture => $pages) {
            $result = $this->preingest($fixture);

            $this->assertOk($result, $fixture);
            $this->assertSame($pages.' '.($pages === 1 ? 'page' : 'pages').' identical', $result->outcomes[0]->details['verification']['detail'], $fixture);
        }
    }

    public function test_files_the_rule_does_not_serve_are_left_alone(): void
    {
        foreach (['deflate_predictor2.tif', 'jpeg.tif', 'g4.tif'] as $fixture) {
            $result = $this->preingest($fixture);

            $this->assertFalse($result->changed(), $fixture);
            $this->assertSame([], $result->outcomes, $fixture.': no event');
        }
    }

    public function test_the_precheck_names_what_it_sees(): void
    {
        $runner = app(CommandRunner::class);

        $this->assertSame([
            'compression' => 'lzw', 'predictor' => 1, 'bits_per_sample' => 8, 'samples_per_pixel' => 3, 'bigtiff' => false,
            'width' => 64, 'height' => 48, 'colour_space' => 'rgb', 'icc_profile' => 'QJ Test RGB D65', 'pages' => 1, 'previews' => 0,
        ], $runner->characterize('tiff_characteristics', $this->fixture('lzw.tif')));
        $this->assertSame('ccitt', $runner->characterize('tiff_characteristics', $this->fixture('g4.tif'))['compression']);
        $this->assertSame(16, $runner->characterize('tiff_characteristics', $this->fixture('rgb16.tif'))['bits_per_sample']);
        $this->assertSame('cmyk', $runner->characterize('tiff_characteristics', $this->fixture('cmyk.tif'))['colour_space']);
        $this->assertSame('gray', $runner->characterize('tiff_characteristics', $this->fixture('gray.tif'))['colour_space']);
        $this->assertSame(3, $runner->characterize('tiff_characteristics', $this->fixture('multipage.tif'))['pages']);

        $preview = $runner->characterize('tiff_characteristics', $this->fixture('preview_ifd1.tif'));
        $this->assertSame([1, 1], [$preview['pages'], $preview['previews']]);
    }

    public function test_the_probe_says_per_file_whether_the_rule_applies_and_why_not(): void
    {
        $probe = fn (string $fixture) => app(Preingest::class)->probe($this->fixture($fixture), 'media_add');

        $uncompressed = $probe('uncompressed.tif');
        $this->assertSame(RuleVerdict::APPLIES, $uncompressed->verdicts[0]->verdict);
        $this->assertSame('fmt/353', $uncompressed->identification->pronomId);
        $this->assertSame('none', $uncompressed->characteristics['tiff_characteristics']['facts']['compression']);
        $this->assertSame('exiftool', $uncompressed->characteristics['tiff_characteristics']['tool']);

        $this->assertSame(['compression' => 'deflate', 'predictor' => 2], $probe('deflate_predictor2.tif')->verdicts[0]->condition);
        $this->assertSame(['compression' => 'jpeg'], $probe('jpeg.tif')->verdicts[0]->condition);
        $this->assertSame(['bits_per_sample' => 1], $probe('g4.tif')->verdicts[0]->condition);
        $this->assertSame(RuleVerdict::OTHER_FORMAT, $probe('blank.pdf')->verdicts[0]->verdict);
    }

    public function test_different_pixels_fail_the_verification(): void
    {
        $verification = app(CommandRunner::class)->verify('pixel_signature', $this->fixture('uncompressed.tif'), $this->fixture('gray.tif'));

        $this->assertFalse($verification->passed);
        $this->assertSame('the pixels differ on page 1', $verification->detail);

        $verification = app(CommandRunner::class)->verify('pixel_signature', $this->fixture('uncompressed.tif'), $this->fixture('multipage.tif'));
        $this->assertSame('1 pages delivered, 3 in the result', $verification->detail);
    }

    public function test_sips_are_not_touched(): void
    {
        $this->assertSame([], $this->preingest('uncompressed.tif', 'sip_ech')->outcomes);
        $this->assertSame([], $this->preingest('uncompressed.tif', 'sip_agate')->outcomes);
    }

    private function assertOk(PreingestResult $result, string $message = ''): void
    {
        $this->assertCount(1, $result->outcomes, $message);
        $this->assertSame(RuleOutcome::OK, $result->outcomes[0]->status, $message.' '.json_encode($result->outcomes[0]->details));
        $this->assertTrue($result->changed(), $message);
    }

    private function preingest(string $fixture, string $source = 'media_add'): PreingestResult
    {
        return $this->results[] = app(Preingest::class)->run($this->fixture($fixture), $source);
    }

    /**
     * A working copy of a fixture; the preingest may consume what it is given.
     */
    private function fixture(string $name): string
    {
        $file = sys_get_temp_dir().'/qj-tiff-zip-'.uniqid().'-'.basename($name);
        copy(__DIR__.'/fixtures/'.$name, $file);
        $this->files[] = $file;

        return $file;
    }

    /**
     * @return array{compression: string, predictor: int}
     */
    private function packing(string $file): array
    {
        $facts = app(CommandRunner::class)->characterize('tiff_characteristics', $file);

        return ['compression' => $facts['compression'], 'predictor' => $facts['predictor']];
    }

    /**
     * The descriptive metadata: IFD0, ExifIFD, XMP, IPTC, ICC — without what
     * describes the encoding (compression, strips, sizes).
     *
     * @return array<string, mixed>
     */
    private function metadata(string $file): array
    {
        $tags = $this->tags($file, ['-IFD0:Artist', '-IFD0:ImageDescription', '-ExifIFD:all', '-XMP:all', '-IPTC:all', '-ICC_Profile:ProfileDescription']);
        ksort($tags);

        return $tags;
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    private function tags(string $file, array $arguments): array
    {
        $output = Process::run(array_merge(['exiftool', '-j', '-G1', '-n'], $arguments, [$file]))->output();
        $tags = json_decode($output, true)[0] ?? [];
        unset($tags['SourceFile']);

        return $tags;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
