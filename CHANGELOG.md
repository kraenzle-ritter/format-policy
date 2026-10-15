# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.2.0] - 2026-10-15

### Added
- `Preingest::probe()`: a dry run for one file that converts nothing. It returns a `Probe` with the identification, the facts each precheck measured (with tool and version), and a `RuleVerdict` for every rule in force on the route: `applies`, `other_format`, `precheck` (with the vetoing `skip_when` entry), `precheck_failed` or `tools_missing` (with the tools). Rules whose tools are missing are named instead of being left out.
- `RuleOverview::for($policy, $store, $runner, ?$source)`: the rules as an archive sees them on its settings page — label, description, phase, default, the tenant's override, in force, whether a route is admitted, the routes, and the tools this server lacks. Retired rules are left out (anton#655).
- View `format-policy::rules` (Bootstrap 5, no Livewire): one switch per rule as `rules[<key>]`, or read only with a note where the rules are set (`managedBy`). Translations `format-policy::rules.*` in de, en, fr, it.
- `RuleSwitches::apply(array $input)`: sets what a settings form sent and returns the changes; unknown and retired keys are ignored, unchanged values are not written.
- `tiff_zip` has its label and description in German, French and Italian.

### Removed
- Deselecting a rule per run: the option `skip_rules` of `run()` and `plan()` and the rule attribute `optional` (with it the `optional` field of `ForecastedRule` and `PlannedRule`). An archive sets its rules once in its settings; a delivery cannot leave one out (anton#655). An `optional` key in a policy is ignored.

## [0.1.0] - 2026-10-08

### Added
- Format policy registry: tools, commands (`identify`, `characterize`, `transform`, `verify`; template or handler) and rules (phase, format match, ingest routes, precheck, transforms, verification, original, priority, default, optional, retired/replaces), read through `FormatPolicyRepository`.
- Catalogue in `resources/catalogue.php`; programs add entries in `config/format-policy.php` but cannot redefine catalogue keys.
- `Preingest::run()`, `plan()`, `rulesInForce()` and `PreingestForecast::forFiles()`; ingest routes as strings or backed enums.
- Per-tenant switches behind `SwitchStore` (`ArraySwitchStore` from config by default); `Preingest::withSwitches()` for programs serving several tenants.
- Rule `tiff_zip` with handlers `SiegfriedIdentify`, `TiffCharacteristics`, `DropAddedExifFields` and `PixelSignature`. The metadata copy keeps the delivered `XMP-x:XMPToolkit`.
- Extracted from Anton, where the policy was introduced; behaviour unchanged.
