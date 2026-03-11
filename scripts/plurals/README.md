# Plural Specs Generation (Scaffold)

This folder contains the future data pipeline for plural specs.

## Goals

- Keep a transparent source of truth for language plural rules.
- Merge public baseline data with project-specific overrides.
- Validate output against a strict contract before writing artifacts.

## Current Scope

This is an initial scaffold only:

- baseline sample input: `cldr-baseline.sample.json`
- override interface: `class-plural-spec-overrides.php`
- default override implementation: `class-project-plural-spec-overrides.php`
- contract validator: `class-spec-contract-validator.php`
- generator CLI: `../generate-plural-specs.php`

No runtime plugin file is modified in this slice.

## Baseline Input Format

Top-level object:

- keys: language codes (`en`, `fr`, `ru`, ...)
- values: objects with:
  - `nplurals` (int >= 1)
  - `categories` (string[])
  - `plural_expression` (string)

Example:

```json
{
  "en": {
    "nplurals": 2,
    "categories": ["one", "other"],
    "plural_expression": "(n != 1)"
  }
}
```

## Generator Usage

Dry run (validation + merge, no output file):

```bash
php scripts/generate-plural-specs.php --dry-run
```

Write generated map:

```bash
php scripts/generate-plural-specs.php \
  --input scripts/plurals/cldr-baseline.sample.json \
  --output scripts/plurals/generated/plural-spec-map.php
```

Write one generated class per language (PSR-4 friendly):

```bash
php scripts/generate-plural-specs.php \
  --input scripts/plurals/cldr-baseline.sample.json \
  --languages-dir plugin/includes/WP_I18nly/Plurals/Languages
```

## Notes

- `ProjectPluralSpecOverrides` is intentionally conservative in this scaffold.
- Add project rules there as the next step.
- Keep overrides deterministic and side-effect free.
