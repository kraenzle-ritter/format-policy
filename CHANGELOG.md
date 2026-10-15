# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.2.0] - 2026-10-15

### Added
- `Preingest::probe()`: a dry run for one file that converts nothing. It returns a `Probe` with the identification, the facts each precheck measured (with tool and version), and a `RuleVerdict` for every rule in force on the route: `applies`, `other_format`, `precheck` (with the vetoing `skip_when` entry), `precheck_failed` or `tools_missing` (with the tools). Rules whose tools are missing are named instead of being left out.

## [0.1.0] - 2026-10-08

### Added
- Format policy registry: tools, commands (`identify`, `characterize`, `transform`, `verify`; template or handler) and rules (phase, format match, ingest routes, precheck, transforms, verification, original, priority, default, optional, retired/replaces), read through `FormatPolicyRepository`.
- Catalogue in `resources/catalogue.php`; programs add entries in `config/format-policy.php` but cannot redefine catalogue keys.
- `Preingest::run()`, `plan()`, `rulesInForce()` and `PreingestForecast::forFiles()`; ingest routes as strings or backed enums.
- Per-tenant switches behind `SwitchStore` (`ArraySwitchStore` from config by default); `Preingest::withSwitches()` for programs serving several tenants.
- Rule `tiff_zip` with handlers `SiegfriedIdentify`, `TiffCharacteristics`, `DropAddedExifFields` and `PixelSignature`. The metadata copy keeps the delivered `XMP-x:XMPToolkit`.
- Extracted from Anton, where the policy was introduced; behaviour unchanged.
