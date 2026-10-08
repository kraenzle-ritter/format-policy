# kraenzle-ritter/format-policy

A format policy registry and preingest for Laravel, on the pattern of
Archivematica's Format Policy Registry (FPR).
Used by Anton (archival description) and agate (SIP
preparation), so that both apply the same rules under the same keys.

Before a file is ingested, the preingest identifies it, applies the rules in
force for the tenant and the route the file came by, verifies each result and
identifies it again. It never fails an ingest: a step that fails, a
verification that does not pass, a result that is not smaller — the file as it
was before that rule is kept, and the outcome is recorded.

## Concepts

| Entry | What it is |
|---|---|
| **Tool** | A program, the names it may go by, and how to ask it for its version |
| **Command** | How a tool is called — `identify`, `characterize`, `transform` or `verify` — by a template or a handler class |
| **Rule** | When and what for: phase, format match (MIME, PRONOM, extension), ingest routes, a precheck that may veto it, transforms, a verification, and what becomes of the original |

Every entry has a stable key and an English label and description. **Keys are
never deleted or given a new meaning**: events recorded on files name them.
Retire a rule and add its successor under a new key with `replaces`.

The tools, commands and rules live in the package's catalogue
(`resources/catalogue.php`). A program may add entries of its own in
`config/format-policy.php`; one that redefines a catalogue key is reported in
`problems()` and ignored.

### Rules in the catalogue

| Key | Default | What it does |
|---|---|---|
| `tiff_zip` | off | Repacks a TIFF losslessly as Deflate with predictor 2 (`tiffcp`), copies its metadata back (`exiftool`) and verifies the pixels page by page (ImageMagick). Skips TIFFs already packed so, JPEG-compressed, bitonal or BigTIFF, and results that are not smaller. Not for SIPs (`sip_ech`, `sip_agate`). |

## Installation

```bash
composer require kraenzle-ritter/format-policy
php artisan vendor:publish --tag=format-policy-config   # optional
```

Tools used by the catalogue: `sf` (Siegfried), `tiffcp` (libtiff-tools),
`exiftool`, `magick` (ImageMagick 7). A rule whose tools are missing on the
server is not applied, and a warning is logged.

## Usage

```php
use KraenzleRitter\FormatPolicy\Preingest;

$result = app(Preingest::class)->run($path, 'upload', [
    'skip_rules' => ['tiff_zip'],                               // optional rules the user deselected
    'delivered' => ['algorithm' => 'sha256', 'value' => '…'],   // checksum that came with the file
]);

$result->path;          // the file to ingest: the delivered one or a result
$result->outcomes;      // list<RuleOutcome>: rule, status (ok|failed|info), details
$result->cleanup();     // after ingesting: removes the working directory
```

The route is a string the program defines (Anton: `upload`, `media_add`,
`sip_ech`, …); a backed enum is accepted by its value.

Each `RuleOutcome` is meant to be stored as an event on the ingested file
(Anton: a `format-normalization` medium event). Its details name the rule, the
route, the tools with their versions, and MD5, SHA-512, size, PRONOM id and
MIME type before and after.

`Preingest::plan()` names the rules that would act on a file without changing
it; `PreingestForecast::forFiles()` estimates, by extension and MIME type
only, how many files of an import a rule may act on.

## Switches per tenant

Whether a rule is in force is decided per tenant by a `SwitchStore`; a rule's
`enabled_by_default` applies where the tenant says nothing. A retired rule is
never in force.

- **One tenant per process** (Anton): bind your own `SwitchStore` in the
  container. Without one, `ArraySwitchStore` reads `format-policy.switches`.
- **Several tenants in one process** (agate): pass each tenant's switches to
  the run.

```php
$preingest = app(Preingest::class)->withSwitches(new ArraySwitchStore(['tiff_zip' => true]));
```

## Testing

```bash
composer test       # PHPUnit; tests needing a missing tool are skipped
composer analyse    # PHPStan
```

The TIFF fixtures are built by `tests/fixtures/make-fixtures.sh`.

## License

MIT
