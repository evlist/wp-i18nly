<!--
SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
SPDX-License-Identifier: GPL-3.0-or-later
-->

# Plural Specs Generation (Scaffold)

This folder contains the future data pipeline for plural specs.

## Goals

- Keep a transparent source of truth for language plural rules.
- Merge public baseline data with project-specific overrides.
- Validate output against a strict contract before writing artifacts.

## Current Scope

This is an initial scaffold only:

- upstream pinned source snapshot: `upstream/glotpress-locales.php`
- override interface: `class-plural-spec-overrides.php`
- default override implementation: `class-project-plural-spec-overrides.php`
- contract validator: `class-spec-contract-validator.php`
- generator CLI: `../generate-plural-specs.php`

Default input is the pinned GlotPress source snapshot:

- `scripts/plurals/upstream/glotpress-locales.php`

No runtime plugin file is modified in this slice.

## Why GlotPress, Not CLDR?

The plural specs pipeline uses GlotPress as the authoritative source of truth instead of CLDR (Common Locale Data Repository) for these reasons:

1. **Single Source of Truth**: GlotPress locales are the definitive reference used by WordPress.org for all translations. Plural rules are directly curated by WordPress translation community leads, avoiding discrepancies between sources.

2. **Active Maintenance**: GlotPress is continuously updated and maintained by the WordPress translation infrastructure. Rules are validated against real-world translation needs rather than theoretical specifications.

3. **Direct Applicability**: GlotPress rules are designed and tested specifically for WordPress translated content. CLDR, while comprehensive, is a general-purpose specification and may include edge cases not relevant to WordPress' translation scope.

4. **Architecturally Simpler**: Using CLDR as a separate source created maintenance fragility—version discovery across JSON files, format parsing variations, and potential divergence from the WordPress-authoritative source. GlotPress eliminates this complexity.

5. **Integration Cost**: CLDR plural rules often require post-processing (expression simplification, edge-case handling, Gettext compatibility). GlotPress rules are already in Gettext-compatible format (`nplurals` and `plural_expression`).

## Upstream Source And License

Canonical upstream source:

- GlotPress locales table: `https://plugins.svn.wordpress.org/glotpress/trunk/locales/locales.php`

Pinned snapshot currently present in this repository:

- `scripts/plurals/upstream/glotpress-locales.php`

License for GlotPress source data:

- GPL-2.0-or-later
- SPDX: `GPL-2.0-or-later`

Update command for latest GlotPress snapshot:

```bash
curl -sSL "https://plugins.svn.wordpress.org/glotpress/trunk/locales/locales.php" -o "scripts/plurals/upstream/glotpress-locales.php"
```

## Supported Input Formats

### GlotPress locales.php (default)

The generator accepts an imported copy of GlotPress `locales.php` defining
`GP_Locales` and uses `GP_Locales::locales()` as source of truth.

### Internal generated format

Top-level object:

- keys: locale codes (`en_US`, `fr_FR`, `pt_BR`, `ja`, ...)
- values: objects with:
  - `nplurals` (int >= 1)
  - `plural_expression` (string)
  - `forms` (map<string, string>)

Example:

```json
{
  "en_US": {
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

Strict audit mode (fail-fast + JSON report):

```bash
php scripts/generate-plural-specs.php \
  --dry-run \
  --audit \
  --audit-report build/plurals-audit.json
```

When `--audit` is enabled, the script fails on:

- `nplurals` / `forms` count mismatches,
- empty `plural_expression` values.

Optional stricter policy:

```bash
php scripts/generate-plural-specs.php \
  --dry-run \
  --audit \
  --audit-fail-on-overrides
```

With `--audit-fail-on-overrides`, any project override usage becomes an audit failure.

By default, the script tries to use WP-CLI to filter generated locales to
WordPress-supported locales (`wp language core list --field=language`), using
exact locale matching after normalization.

If WP-CLI is unavailable, the script falls back to generating all baseline locales.

By default, the script writes one generated class per locale to:

- `plugin/includes/WP_I18nly/Plurals/Languages`

Write one generated class per locale (PSR-4 friendly):

```bash
php scripts/generate-plural-specs.php \
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

When WP filtering is enabled, the script also reports locales supported by WP
but missing from the current GlotPress baseline snapshot.

## WordPress / Gettext Boundaries

For plural rules source-of-truth, tooling roles are intentionally distinct:

- WordPress / WP-CLI `language core list` provides available locales only.
- WP-CLI i18n code consumes `Plural-Forms` headers when present in translation files.
- gettext tooling can parse PO headers, but does not define product policy.

Therefore, this pipeline uses GlotPress locale definitions as baseline source and treats WP/gettext as consumers of those rules.

## Notes

- `ProjectPluralSpecOverrides` is intentionally conservative in this scaffold.
- Override matching receives canonical locales (example: `en_US`, `pt_BR`, `ja`).
- Add project rules there as the next step.
- Keep overrides deterministic and side-effect free.
