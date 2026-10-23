<?php

use KraenzleRitter\FormatPolicy\Handlers\DropAddedExifFields;
use KraenzleRitter\FormatPolicy\Handlers\PixelSignature;
use KraenzleRitter\FormatPolicy\Handlers\SiegfriedIdentify;
use KraenzleRitter\FormatPolicy\Handlers\StreamSignature;
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

        'ffmpeg' => [
            'label' => ['en' => 'FFmpeg'],
            'description' => ['en' => 'Reads, converts and decodes audio and video. Used here to hash the decoded frames of every stream (ffmpeg) and to read the streams of a file (ffprobe, beside it), which proves that a lossless conversion kept every sample and every pixel.'],
            'binaries' => ['ffmpeg'],
            'version_command' => '{binary} -version',
            'version_pattern' => '/ffmpeg version n?(\d+(?:\.\d+)+)/',
        ],

    ],

    'commands' => [

        'siegfried' => [
            'label' => ['en' => 'Identify the format', 'de' => 'Format bestimmen', 'fr' => 'Identifier le format', 'it' => 'Identificare il formato'],
            'description' => ['en' => 'Runs Siegfried on a file and returns its PRONOM id, MIME type and format name.'],
            'kind' => 'identify',
            'tool' => 'siegfried',
            'handler' => SiegfriedIdentify::class,
            'timeout' => 60,
        ],

        'tiff_characteristics' => [
            'label' => ['en' => 'Read TIFF properties', 'de' => 'TIFF-Merkmale lesen', 'fr' => 'Lire les propriétés du TIFF', 'it' => 'Leggere le proprietà del TIFF'],
            'description' => ['en' => 'Reads compression, predictor, bits per sample and whether the file is a BigTIFF, so that a rule can leave files alone that are already in the target form or that the conversion would not serve.'],
            'kind' => 'characterize',
            'tool' => 'exiftool',
            'handler' => TiffCharacteristics::class,
            'timeout' => 60,
        ],

        'tiffcp_zip' => [
            'label' => ['en' => 'Rewrite TIFF as Deflate with predictor 2', 'de' => 'TIFF als Deflate mit Predictor 2 neu schreiben', 'fr' => 'Réécrire le TIFF en Deflate avec prédicteur 2', 'it' => 'Riscrivere il TIFF in Deflate con predittore 2'],
            'description' => ['en' => 'Writes a copy of the TIFF with lossless Deflate (Zip) compression and horizontal predictor 2. The pixels stay the same; EXIF sub-IFD, XMP and IPTC are lost here and restored by the next step.'],
            'kind' => 'transform',
            'tool' => 'tiffcp',
            'template' => '{binary} -c zip:2 {source} {target}',
            'timeout' => 600,
        ],

        'exiftool_copy_metadata' => [
            'label' => ['en' => 'Copy metadata from the original', 'de' => 'Metadaten der gelieferten Datei übernehmen', 'fr' => 'Reprendre les métadonnées du fichier livré', 'it' => 'Riprendere i metadati del file consegnato'],
            'description' => ['en' => 'Copies all metadata of the delivered file into the converted one, except the IFD1 preview tags (tiffcp keeps the preview itself) and the IPTC character set, which exiftool would otherwise add. The XMP toolkit is copied back explicitly: exiftool puts its own name there whenever it writes XMP.'],
            'kind' => 'transform',
            'tool' => 'exiftool',
            'template' => '{binary} -q -q -m -n -overwrite_original -tagsFromFile {original} -all:all --IFD1:all -IPTC:CodedCharacterSet "-XMP-x:XMPToolkit<XMP-x:XMPToolkit" {target}',
            'in_place' => true,
            'timeout' => 120,
        ],

        'exiftool_drop_added' => [
            'label' => ['en' => 'Remove fields ExifTool added', 'de' => 'Von ExifTool ergänzte Felder entfernen', 'fr' => 'Supprimer les champs ajoutés par ExifTool', 'it' => 'Rimuovere i campi aggiunti da ExifTool'],
            'description' => ['en' => 'ExifTool fills in mandatory fields the original never had (ColorSpace, ComponentsConfiguration, FlashpixVersion, EnvelopeRecordVersion). They are removed again where the original lacks them, so the metadata is the delivered one.'],
            'kind' => 'transform',
            'tool' => 'exiftool',
            'handler' => DropAddedExifFields::class,
            'in_place' => true,
            'timeout' => 120,
        ],

        'pixel_signature' => [
            'label' => ['en' => 'Compare pixel signatures', 'de' => 'Pixel-Signaturen vergleichen', 'fr' => 'Comparer les signatures des pixels', 'it' => 'Confrontare le firme dei pixel'],
            'description' => ['en' => 'Computes a signature of the decoded pixels of every page of the original and the result. They must be identical: a lossless conversion that changed a pixel is no conversion to keep.'],
            'kind' => 'verify',
            'tool' => 'imagemagick',
            'handler' => PixelSignature::class,
            'timeout' => 600,
        ],

        'stream_signature' => [
            'label' => ['en' => 'Compare stream signatures', 'de' => 'Prüfsummen der Spuren vergleichen', 'fr' => 'Comparer les sommes de contrôle des pistes', 'it' => 'Confrontare le somme di controllo delle tracce'],
            'description' => ['en' => 'Computes an MD5 of the decoded frames of every audio and video stream of the original and the result, and compares the streams, channels, sample rate, picture size, frame rate and duration. They must be identical: a container change, FFV1 or FLAC keeps every sample and every pixel.'],
            'kind' => 'verify',
            'tool' => 'ffmpeg',
            'handler' => StreamSignature::class,
            'timeout' => 3600,
        ],

    ],

    'rules' => [

        'tiff_zip' => [
            'label' => [
                'en' => 'Compress TIFF losslessly (Deflate, predictor 2)',
                'de' => 'TIFF verlustfrei komprimieren (Deflate, Predictor 2)',
                'fr' => 'Compresser les TIFF sans perte (Deflate, prédicteur 2)',
                'it' => 'Comprimere i TIFF senza perdita (Deflate, predittore 2)',
            ],
            'description' => [
                'en' => 'Uncompressed TIFF, or LZW without a predictor, as cameras and scanning software deliver it, becomes 38–66 % smaller as Deflate with predictor 2, without a single pixel changing. The converted file becomes the archival master once its pixels are proven identical; the delivered file is discarded, its checksums stay in the event on the medium. Left alone: files already in that form, JPEG-compressed TIFF, bitonal (CCITT G4 compresses better) and BigTIFF. Not applied to SIPs, whose own checksums prove an untouched delivery.',
                'de' => 'Unkomprimierte TIFF oder LZW ohne Predictor, wie Kameras und Scan-Software sie liefern, werden mit Deflate und Predictor 2 um 38–66 % kleiner, ohne dass sich ein einziges Pixel ändert. Die umgewandelte Datei wird zum Archivmaster, sobald ihre Pixel als identisch nachgewiesen sind; die gelieferte Datei wird verworfen, ihre Prüfsummen bleiben im Ereignis am Medium. Unverändert bleiben: Dateien, die schon so vorliegen, JPEG-komprimierte TIFF, bitonale Bilder (CCITT G4 komprimiert besser) und BigTIFF. Nicht angewendet auf SIPs, deren eigene Prüfsummen eine unveränderte Ablieferung belegen.',
                'fr' => 'Les TIFF non compressés, ou en LZW sans prédicteur, tels que les livrent les appareils photo et les logiciels de numérisation, deviennent 38 à 66 % plus petits en Deflate avec prédicteur 2, sans qu\'un seul pixel ne change. Le fichier converti devient le master d\'archivage dès que l\'identité de ses pixels est prouvée ; le fichier livré est écarté, ses sommes de contrôle restent dans l\'événement du média. Restent inchangés : les fichiers déjà sous cette forme, les TIFF compressés en JPEG, les images bitonales (CCITT G4 compresse mieux) et les BigTIFF. Non appliqué aux SIP, dont les propres sommes de contrôle prouvent une livraison intacte.',
                'it' => 'I TIFF non compressi, o in LZW senza predittore, come li forniscono fotocamere e software di scansione, diventano più piccoli del 38–66 % con Deflate e predittore 2, senza che cambi un solo pixel. Il file convertito diventa il master d\'archivio non appena è dimostrata l\'identità dei suoi pixel; il file consegnato viene scartato, le sue somme di controllo restano nell\'evento del media. Restano invariati: i file già in questa forma, i TIFF compressi in JPEG, le immagini bitonali (CCITT G4 comprime meglio) e i BigTIFF. Non applicato ai SIP, le cui somme di controllo dimostrano una consegna intatta.',
            ],
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
        ],

    ],

];
