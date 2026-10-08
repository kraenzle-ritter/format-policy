# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-10-08

### Added
- Format policy registry: tools, commands (`identify`, `characterize`, `transform`, `verify`; template or handler) and rules (phase, format match, ingest routes, precheck, transforms, verification, original, priority, default, optional, retired/replaces), read through `FormatPolicyRepository`.
- Catalogue in `resources/catalogue.php`; programs add entries in `config/format-policy.php` but cannot redefine catalogue keys.
- `Preingest::run()`, `plan()`, `rulesInForce()` and `PreingestForecast::forFiles()`; ingest routes as strings or backed enums.
- Per-tenant switches behind `SwitchStore` (`ArraySwitchStore` from config by default); `Preingest::withSwitches()` for programs serving several tenants.
- Rule `tiff_zip` with handlers `SiegfriedIdentify`, `TiffCharacteristics`, `DropAddedExifFields` and `PixelSignature`. The metadata copy keeps the delivered `XMP-x:XMPToolkit`.
- Extracted from Anton, where the policy was introduced; behaviour unchanged.
