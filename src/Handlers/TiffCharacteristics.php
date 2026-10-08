<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Handlers;

use Illuminate\Support\Facades\Process;
use KraenzleRitter\FormatPolicy\Command;
use KraenzleRitter\FormatPolicy\CommandFailed;

/**
 * The properties of a TIFF, read with exiftool: those that decide whether
 * `tiff_zip` should touch it (compression, predictor, bits per sample,
 * BigTIFF) and those that describe the master on the medium (size, colour
 * space, ICC profile, pages). Encoding properties come from the first image
 * (IFD0); pages and previews are counted over all of them.
 */
final class TiffCharacteristics implements CharacterizesFile
{
    /**
     * TIFF compression codes by the names a rule's `skip_when` uses.
     *
     * @var array<int, string>
     */
    private const COMPRESSION = [
        1 => 'none',
        2 => 'ccitt',
        3 => 'ccitt',
        4 => 'ccitt',
        5 => 'lzw',
        6 => 'jpeg',
        7 => 'jpeg',
        8 => 'deflate',
        32773 => 'packbits',
        32946 => 'deflate',
        34712 => 'jpeg2000',
    ];

    /**
     * PhotometricInterpretation by colour space.
     *
     * @var array<int, string>
     */
    private const COLOUR_SPACE = [
        0 => 'gray',
        1 => 'gray',
        2 => 'rgb',
        3 => 'palette',
        5 => 'cmyk',
        6 => 'ycbcr',
        8 => 'lab',
        9 => 'lab',
        10 => 'lab',
    ];

    /**
     * @return array{compression: string, predictor: int, bits_per_sample: int, samples_per_pixel: int, bigtiff: bool, width: int, height: int, colour_space: string, icc_profile: string|null, pages: int, previews: int}
     */
    public function characterize(Command $command, string $binary, string $file): array
    {
        $result = Process::timeout($command->timeout)->run([
            $binary, '-j', '-n', '-a', '-G1', '-FileType', '-Compression', '-Predictor', '-BitsPerSample',
            '-SamplesPerPixel', '-ImageWidth', '-ImageHeight', '-PhotometricInterpretation', '-SubfileType',
            '-ICC_Profile:ProfileDescription', $file,
        ]);
        $tags = json_decode($result->output(), true)[0] ?? null;

        if (! $result->successful() || ! is_array($tags) || ! isset($tags['IFD0:Compression'])) {
            throw new CommandFailed("«{$command->key}» could not read the TIFF properties: ".trim($result->errorOutput()));
        }

        $compression = (int) $tags['IFD0:Compression'];
        $photometric = (int) ($tags['IFD0:PhotometricInterpretation'] ?? -1);
        $profile = trim((string) ($tags['ICC_Profile:ProfileDescription'] ?? ''));
        [$pages, $previews] = $this->countImages($tags);

        return [
            'compression' => self::COMPRESSION[$compression] ?? 'other:'.$compression,
            'predictor' => (int) ($tags['IFD0:Predictor'] ?? 1),
            'bits_per_sample' => (int) strtok((string) ($tags['IFD0:BitsPerSample'] ?? '0'), ' '),
            'samples_per_pixel' => (int) ($tags['IFD0:SamplesPerPixel'] ?? 1),
            'bigtiff' => ($tags['File:FileType'] ?? '') === 'BTF',
            'width' => (int) ($tags['IFD0:ImageWidth'] ?? 0),
            'height' => (int) ($tags['IFD0:ImageHeight'] ?? 0),
            'colour_space' => self::COLOUR_SPACE[$photometric] ?? 'other:'.$photometric,
            'icc_profile' => $profile !== '' ? $profile : null,
            'pages' => $pages,
            'previews' => $previews,
        ];
    }

    /**
     * Full pages and reduced images (SubfileType bit 1), one per IFD.
     *
     * @param  array<string, mixed>  $tags
     * @return array{0: int, 1: int}
     */
    private function countImages(array $tags): array
    {
        $pages = 0;
        $previews = 0;
        foreach ($tags as $key => $value) {
            if (preg_match('/^(IFD\d+):ImageWidth$/', (string) $key, $match) !== 1) {
                continue;
            }
            if (((int) ($tags[$match[1].':SubfileType'] ?? 0) & 1) === 1) {
                $previews++;
            } else {
                $pages++;
            }
        }

        return [$pages, $previews];
    }
}
