<?php

use KraenzleRitter\FormatPolicy\Handlers\DropAddedExifFields;
use KraenzleRitter\FormatPolicy\Handlers\PixelSignature;
use KraenzleRitter\FormatPolicy\Handlers\SiegfriedIdentify;
use KraenzleRitter\FormatPolicy\Handlers\TiffCharacteristics;

/*
|--------------------------------------------------------------------------
| The format policy catalogue
|--------------------------------------------------------------------------
|
| What a program does to a file, by format. Three kinds of entries, each
| under a stable key:
|
|   tools     a program, and how to ask it for its version
|   commands  how a tool is called: transform | verify | identify | characterize
|   rules     when and what for: phase, match, ingest routes, precheck,
|             transforms, verify, what becomes of the original, default
|
| Shared by every program using this package (Anton, agate): a key means the
| same everywhere, because events on files name it. Entries are never
| deleted or given a new meaning — retire a rule (`retired => true`) and add
| its successor under a new key with `replaces`. A program may add entries
| of its own in config/format-policy.php, never redefine these.
|
| Whether a rule is in force is decided per tenant by a SwitchStore;
| `enabled_by_default` is the value where the tenant says nothing.
|
| Labels and descriptions are language maps; English for now, other
| languages fall back to it.
|
| Placeholders in templates:
|   {binary}    the tool's program found on this server
|   {source}    the file as it stands before this step
|   {target}    the file this step writes (in_place: same as {source})
|   {original}  the file as delivered, before the rule began
*/

return [

    'tools' => [

        'tiffcp' => [
            'label' => ['en' => 'tiffcp (libtiff)'],
            'description' => ['en' => 'Copies TIFF files from libtiff. Rewrites the image data with another compression while keeping the file structure, the reduced-resolution preview in IFD1 and the ICC profile.'],
            'binaries' => ['tiffcp'],
            'version_command' => '{binary}',
            'version_pattern' => '/Version (\d+(?:\.\d+)+)/',
        ],

        'exiftool' => [
            'label' => ['en' => 'ExifTool'],
            'description' => ['en' => 'Reads and writes metadata (EXIF, IPTC, XMP, ICC) in image files. Used to carry metadata over that tiffcp drops, and to read TIFF properties.'],
            'binaries' => ['exiftool'],
            'version_command' => '{binary} -ver',
            'version_pattern' => '/^(\d+(?:\.\d+)+)/m',
        ],

        'imagemagick' => [
            'label' => ['en' => 'ImageMagick'],
            'description' => ['en' => 'Image toolkit. Used here only to read a signature of the decoded pixels of every page, which proves that a lossless conversion changed no pixel. ImageMagick 7 calls it as `magick identify`, version 6 as `identify`.'],
            'binaries' => ['magick', 'identify'],
            'version_command' => '{binary} -version',
            'version_pattern' => '/ImageMagick (\d+(?:\.\d+)+(?:-\d+)?)/',
        ],

        'siegfried' => [
            'label' => ['en' => 'Siegfried'],
            'description' => ['en' => 'Identifies file formats against the PRONOM registry and names the PRONOM id and MIME type.'],
            'binaries' => ['sf'],
            'version_command' => '{binary} -version',
            'version_pattern' => '/siegfried (\d+(?:\.\d+)+)/',
        ],

    ],

    'commands' => [

        'siegfried' => [
            'label' => ['en' => 'Identify the format'],
            'description' => ['en' => 'Runs Siegfried on a file and returns its PRONOM id, MIME type and format name.'],
            'kind' => 'identify',
            'tool' => 'siegfried',
            'handler' => SiegfriedIdentify::class,
            'timeout' => 60,
        ],

        'tiff_characteristics' => [
            'label' => ['en' => 'Read TIFF properties'],
            'description' => ['en' => 'Reads compression, predictor, bits per sample and whether the file is a BigTIFF, so that a rule can leave files alone that are already in the target form or that the conversion would not serve.'],
            'kind' => 'characterize',
            'tool' => 'exiftool',
            'handler' => TiffCharacteristics::class,
            'timeout' => 60,
        ],

        'tiffcp_zip' => [
            'label' => ['en' => 'Rewrite TIFF as Deflate with predictor 2'],
            'description' => ['en' => 'Writes a copy of the TIFF with lossless Deflate (Zip) compression and horizontal predictor 2. The pixels stay the same; EXIF sub-IFD, XMP and IPTC are lost here and restored by the next step.'],
            'kind' => 'transform',
            'tool' => 'tiffcp',
            'template' => '{binary} -c zip:2 {source} {target}',
            'timeout' => 600,
        ],

        'exiftool_copy_metadata' => [
            'label' => ['en' => 'Copy metadata from the original'],
            'description' => ['en' => 'Copies all metadata of the delivered file into the converted one, except the IFD1 preview tags (tiffcp keeps the preview itself) and the IPTC character set, which exiftool would otherwise add. The XMP toolkit is copied back explicitly: exiftool puts its own name there whenever it writes XMP.'],
            'kind' => 'transform',
            'tool' => 'exiftool',
            'template' => '{binary} -q -q -m -n -overwrite_original -tagsFromFile {original} -all:all --IFD1:all -IPTC:CodedCharacterSet "-XMP-x:XMPToolkit<XMP-x:XMPToolkit" {target}',
            'in_place' => true,
            'timeout' => 120,
        ],

        'exiftool_drop_added' => [
            'label' => ['en' => 'Remove fields ExifTool added'],
            'description' => ['en' => 'ExifTool fills in mandatory fields the original never had (ColorSpace, ComponentsConfiguration, FlashpixVersion, EnvelopeRecordVersion). They are removed again where the original lacks them, so the metadata is the delivered one.'],
            'kind' => 'transform',
            'tool' => 'exiftool',
            'handler' => DropAddedExifFields::class,
            'in_place' => true,
            'timeout' => 120,
        ],

        'pixel_signature' => [
            'label' => ['en' => 'Compare pixel signatures'],
            'description' => ['en' => 'Computes a signature of the decoded pixels of every page of the original and the result. They must be identical: a lossless conversion that changed a pixel is no conversion to keep.'],
            'kind' => 'verify',
            'tool' => 'imagemagick',
            'handler' => PixelSignature::class,
            'timeout' => 600,
        ],

    ],

    'rules' => [

        'tiff_zip' => [
            'label' => ['en' => 'Compress TIFF losslessly (Deflate, predictor 2)'],
            'description' => ['en' => 'Uncompressed TIFF, or LZW without a predictor, as cameras and scanning software deliver it, becomes 38–66 % smaller as Deflate with predictor 2, without a single pixel changing. The converted file becomes the archival master once its pixels are proven identical; the delivered file is discarded, its checksums stay in the event on the medium. Left alone: files already in that form, JPEG-compressed TIFF, bitonal (CCITT G4 compresses better) and BigTIFF. Not applied to SIPs, whose own checksums prove an untouched delivery.'],
            'phase' => 'preingest',
            'match' => [
                'mime_types' => ['image/tiff'],
                'pronom_ids' => ['fmt/353', 'fmt/7', 'fmt/8', 'fmt/9', 'fmt/10'],
                'extensions' => ['tif', 'tiff'],
            ],
            'exclude_sources' => ['sip_ech', 'sip_agate'],
            'precheck' => [
                'command' => 'tiff_characteristics',
                'skip_when' => [
                    ['compression' => 'deflate', 'predictor' => 2],
                    ['compression' => 'jpeg'],
                    ['bits_per_sample' => 1],
                    ['bigtiff' => true],
                ],
            ],
            'transform' => ['tiffcp_zip', 'exiftool_copy_metadata', 'exiftool_drop_added'],
            'verify' => 'pixel_signature',
            'require_smaller' => true,
            'original' => 'replace',
            'priority' => 100,
            'enabled_by_default' => false,
            'optional' => true,
        ],

    ],

];
