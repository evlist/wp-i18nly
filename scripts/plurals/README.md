# Plural Specs Generation (Scaffold)

This folder contains the future data pipeline for plural specs.

## Goals

- Keep a transparent source of truth for language plural rules.
- Merge public baseline data with project-specific overrides.
- Validate output against a strict contract before writing artifacts.

## Current Scope

This is an initial scaffold only:

- baseline sample input: `cldr-baseline.sample.json`
- upstream pinned source snapshot: `upstream/plurals-48.1.0.json`
- override interface: `class-plural-spec-overrides.php`
- default override implementation: `class-project-plural-spec-overrides.php`
- contract validator: `class-spec-contract-validator.php`
- generator CLI: `../generate-plural-specs.php`

No runtime plugin file is modified in this slice.

## Upstream Source And License

Canonical upstream source:

- CLDR XML (canonical): `https://github.com/unicode-org/cldr/blob/main/common/supplemental/plurals.xml`
- CLDR JSON (ingestion-friendly): `https://github.com/unicode-org/cldr-json/blob/main/cldr-json/cldr-core/supplemental/plurals.json`

Pinned snapshot currently used in this repository:

- `scripts/plurals/upstream/plurals-48.1.0.json`

License for CLDR and CLDR JSON data:

- Unicode License v3
- SPDX: `Unicode-3.0`

Update command for latest CLDR JSON release snapshot:

```bash
TAG=$(curl -sSL https://api.github.com/repos/unicode-org/cldr-json/releases/latest | grep '"tag_name"' | head -1 | sed -E 's/.*"([^"]+)".*/\1/')
curl -sSL "https://raw.githubusercontent.com/unicode-org/cldr-json/${TAG}/cldr-json/cldr-core/supplemental/plurals.json" -o "scripts/plurals/upstream/plurals-${TAG}.json"
```

## Baseline Input Format

Top-level object:

- keys: language codes (`en`, `fr`, `ru`, ...)
- values: objects with:
  - `nplurals` (int >= 1)
  - `plural_expression` (string)
  - `forms` (map<string, string>)

Example:

```json
{
  "en": {
    "nplurals": 2,
    "plural_expression": "(n != 1)",
    "forms": {
      "1": "One",
      "n": "Other than one"
    }
  }
}
```

## Generator Usage

Dry run (validation + merge, no output file):

```bash
php scripts/generate-plural-specs.php --dry-run
```

By default, the script tries to use WP-CLI to filter generated languages to
WordPress-supported locales (`wp language core list --field=language`), reduced
to two-letter prefixes.

If WP-CLI is unavailable, the script falls back to generating all baseline languages.

By default, the script writes one generated class per language to:

- `plugin/includes/WP_I18nly/Plurals/Languages`

Write one generated class per language (PSR-4 friendly):

```bash
php scripts/generate-plural-specs.php \
  --input scripts/plurals/cldr-baseline.sample.json \
  --languages-dir plugin/includes/WP_I18nly/Plurals/Languages
```

Override the WP locale command explicitly:

```bash
php scripts/generate-plural-specs.php \
  --wp-locales-command="wp language core list --field=language"
```

Disable WP filtering explicitly:

```bash
php scripts/generate-plural-specs.php --wp-locales-command=""
```

When WP filtering is enabled, the script also reports prefixes supported by WP
but missing from the current CLDR baseline snapshot.

## Notes

- `ProjectPluralSpecOverrides` is intentionally conservative in this scaffold.
- Add project rules there as the next step.
- Keep overrides deterministic and side-effect free.
