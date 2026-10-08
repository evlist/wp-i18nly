<!--
SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
SPDX-License-Identifier: GPL-3.0-or-later
-->

# Project audit (2026-10-08)

Scope: whole repository at commit 3de207f. Method: code reading, grep-based checks, CI log analysis,
`composer audit` (no advisory), `npm audit` (0 vulnerabilities). **Not done**: static analysis (phpstan/psalm
could not be installed in the audit sandbox), test on a real WordPress + MySQL, DeepL live tests.
Items marked *(to verify)* are hypotheses from code reading.

## Blocking (fix before any new feature)

| # | Area | Finding |
|---|------|---------|
| B1 | Security | `source_slug` and target language are saved from `$_POST` without validation (`TranslationSaveHandler` l.121-126). `PluginSourceFiles::resolve_main_file()` only strips leading slashes and accepts any readable path under `WP_PLUGIN_DIR`, so `../../x` escapes the plugin directory. The extractor then lists and reads PHP/JS/JSON files from `dirname()` of that file. Impact: arbitrary directory enumeration and extraction of translatable strings from non-plugin code. |
| B2 | Security | The CPT is registered without `capability_type`/`capabilities` (`AdminRegistration` l.52-56): defaults to `post`, so any user with `edit_posts` (Contributor/Author) can create and edit translations by URL even though the menu requires `manage_options`. Combined with B1, and with the AJAX handlers that only check `edit_post` on the translation, those users can trigger DeepL calls (quota consumption) and POT generation. |
| B3 | Data integrity | Translations are passed through `sanitize_text_field()` on save (`TranslationSaveHandler` l.215) and AI source text likewise (`TranslationBatchTranslator` l.149, `TranslationAiAjaxHandler` l.113). This strips tags and collapses newlines/tabs: HTML in translations (`<a href>`, `<strong>`), `%1$s` placeholders next to markup, and multi-line strings are silently altered. The entries payload also goes through `sanitize_textarea_field()` before `json_decode` in the fallback path. Translations must be stored raw and sanitised at output/export time. |
| B4 | CI | CI is red: Plugin Check fails (direct `fwrite` in `Support/FileLockThrottle.php`, `Tested up to` 6.9 < 7.1, Stable tag 0.1.0 ≠ plugin version 0.1.1) and REUSE lint is therefore skipped. |
| B5 | Product | No export: there is no PO/MO/JSON generation from a translation, only the editing UI and a POT. Without it the plugin cannot deliver a usable translation. The vendored `plugin/third-party/wp-cli` i18n-command (unused, not autoloaded) duplicates the homemade extractor and is the likely basis for MO/JSON/make-json. |

## Important

- **I1 No lifecycle**: no activation/uninstall hooks. `uninstall.php` is missing: custom tables, options (`i18nly_source_schema_version`, DeepL settings incl. the API key) and CPT posts survive plugin deletion.
- **I2 No migrations**: `dbDelta` never alters existing keys; schema 0.4.0 requires a manual DB reset in development. Needs a versioned migration step before any release.
- **I3 DeepL API key** stored in plain text in an option (acceptable if documented; a `wp-config.php` constant override is advisable). Passed through `sanitize_text_field`.
- **I4 Concurrency**: the editor saves the full payload; two editors on the same translation: last write wins, no version/lock check. *(to verify at runtime)*
- **I5 Scale**: list table has a hard 500-row cap and per-row queries (N+1) *(to verify)*. Large plugins (WooCommerce ~ 6k strings) will be truncated or slow.
- **I6 Plugin UI not internationalised in JS** (strings in `assets/js` are hard-coded or passed ad hoc); no `wp_set_script_translations`; `languages/` absent although the text domain is used.
- **I7 Version/requirements drift**: header says PHP ≥ 8.1, `composer.json` says ≥ 8.0; `readme.txt` is stale (feature list, tested-up-to, stable tag).
- **I8 Extractor gaps**: `\__()` (leading backslash), typed TS, some JS call forms are ignored (documented in IA.md); a golden test locks the current behaviour, not the correctness.
- **I9 Restore of trashed translations** loses entries (documented decision pending).

## Minor / hygiene

- `AdminPage` still 654 lines (target 400): menu, screens, AJAX registration and row actions remain mixed.
- JS tests (65) and `tests/js` are not part of CI (options in `tests/js/README.md`).
- Nonce checks are duplicated inline (kept for WPCS); a shared helper needs a phpcs annotation strategy.
- `Plurals/Languages/Lang*.php` are generated from a GlotPress snapshot without a documented regeneration command *(to verify)*.
- No static analysis in CI; phpstan level 5 should be introduced with a baseline.
- Dependencies: vendored gettext/peast have no known advisory; `composer audit` and `npm audit` are clean.

## Impact on upcoming slices

- **Slice 5 (glossary editor UI)**: the generic editor is translation-centric (source → N target forms with plural data). Reusing it for glossaries (variants, `match_mode`) is possible but B3 (sanitisation) must be fixed first, otherwise glossary terms are corrupted the same way. Recommend splitting: 5a write a thin dedicated glossary screen using the existing storage; 5b generalise later if duplication proves real.
- **Slice 6 (glossary ↔ translation links, QA, DeepL sync)**: DeepL glossaries only support exact term matching per language pair, and `partial` matches cannot be pushed; scope the sync to `exact` entries and do QA locally. Not before B5 (export), since QA checks need the final strings.
- **Recommended order**: (1) B1+B2+B3 with tests, (2) B4 CI green, (3) I1+I2 lifecycle/migrations, (4) B5 export (PO/MO/JSON, evaluate reuse of vendored wp-cli code or removal of it), (5) I5/I4 robustness, (6) slice 5a, (7) slice 6 reduced to exact terms.

## Slice mapping

The findings are planned as slices H1-H7 in `docs/linguistic-resource-refactoring.md`:
B1, B2 -> H1; B3 -> H2; B4 and I7 -> H3; I1, I2, I3 -> H4; B5 -> H5; I4, I5, I9 -> H6; I6, I8 and minor items -> H7.

## Addendum: vendored wp-cli code

Checked after the audit: `third-party/wp-cli/src` cannot be used as is. It is written for `gettext/gettext` 4.x
(`Gettext\Extractors`, `Gettext\Utils\ParsedComment`, `Gettext\Merge`) and WP-CLI (`WP_CLI`, `WP_CLI\Utils`, command classes: 12 of 21
files reference them), while the plugin requires gettext 5.7, an incompatible API. This is why the extractors were re-implemented
on gettext 5 (`Build/`). Conclusion: B5 is not "reuse wp-cli" but "port the needed generators (MO is native in gettext 5; JED about 80
lines; PHP array about 190 lines)". The copy stays as a reference; see `IA.md`, Third-Party Code. Open question for H5: delete the copy once the
extractor gaps are ported, to avoid carrying 170 KB of unused code. Also noted: `PotGenerator` and `PotSourceImporter` repeat a `require_once` of the autoloader already loaded by `i18nly.php`.
