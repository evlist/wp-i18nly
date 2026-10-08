<!-- SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com> -->
<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# Linguistic Resource Refactoring Plan

## Purpose

This note turns the current architectural direction into an operational refactoring plan.

It is intentionally more concrete than `IA.md` and is meant to guide implementation slices.

## Working Assumptions

### No legacy migration

This project has no installed legacy base to preserve.

Practical consequence:

- no data migration needs to be designed,
- no compatibility layer is needed for old schemas,
- the simplest valid strategy is to drop and recreate the database when the storage model changes.

This should remain the default assumption unless the project later acquires real external installations.

### Terminology

Use the following rule consistently:

- `Translation` and `Glossary` remain user-facing product terms,
- `Linguistic Resource` is the generic developer term,
- `translation` and `glossary` are concrete resource kinds.

### Architectural stance

The main differentiator should not be a thin facade alone.

The preferred implementation model is:

- abstract base classes for shared linguistic-resource behavior,
- concrete derived classes for translation-specific and glossary-specific behavior,
- PHP and JavaScript following the same conceptual split.

A composition root or admin facade may still exist, but it should not carry the core specialization logic.

## Refactoring Goal

Replace the current translation-only structural model with a shared linguistic-resource model in which:

- common behavior lives in abstract PHP and JS classes,
- translation and glossary behavior live in derived classes,
- storage is resource-centric rather than translation-centric,
- the first glossary implementation reuses the same editing and persistence concepts as translations.

## Target Conceptual Model

### Generic domain object

A linguistic resource is a source-target aligned collection of entries plus metadata describing how it is authored, edited, persisted, matched, and compiled.

Shared concepts:

- resource identity,
- source locale,
- target locale,
- entries,
- target forms,
- plural-aware mapping,
- status/provenance or variant metadata,
- optional links to other resources,
- optional compilation state for provider synchronization.

### Specializations

#### Translation

A translation resource is characterized by:

- source entries extracted from a plugin catalog,
- exact identity of source strings,
- one expected target value per relevant plural form,
- integration with WordPress translation build/runtime workflows.

#### Glossary

A glossary resource is characterized by:

- user-authored or curated source terms,
- possible exact and partial matching modes,
- one preferred target plus optional alternates,
- use in QA and provider glossary synchronization rather than direct WordPress gettext lookup.

## Recommended PHP Design

### Abstract base classes

The core shared behavior should live in abstract classes rather than in one large service class.

Recommended first layer:

1. `AbstractLinguisticResource`
   - identity and common metadata,
   - resource-kind contract,
   - entry access,
   - serialization hooks,
   - validation hooks.

2. `AbstractLinguisticResourceEntry`
   - source-side identity,
   - source text and optional plural source text,
   - target access by form,
   - ordering/context metadata.

3. `AbstractLinguisticResourceTarget`
   - target text,
   - form index,
   - shared metadata container.

4. `AbstractLinguisticResourceRepository`
   - load/save one resource,
   - load/save entries and targets,
   - delete/reset operations,
   - shared transaction boundaries.

5. `AbstractLinguisticResourceEditorModel`
   - edit-screen view model assembly,
   - payload normalization,
   - validation and persistence orchestration.

### Concrete PHP classes

Recommended first concrete classes:

1. `TranslationResource extends AbstractLinguisticResource`
2. `GlossaryResource extends AbstractLinguisticResource`
3. `TranslationResourceEntry extends AbstractLinguisticResourceEntry`
4. `GlossaryResourceEntry extends AbstractLinguisticResourceEntry`
5. `TranslationResourceRepository extends AbstractLinguisticResourceRepository`
6. `GlossaryResourceRepository extends AbstractLinguisticResourceRepository`
7. `TranslationEditorModel extends AbstractLinguisticResourceEditorModel`
8. `GlossaryEditorModel extends AbstractLinguisticResourceEditorModel`

### Shared-vs-derived rule

Put behavior in the abstract layer only if both resource kinds need it with the same invariants.

Examples of shared responsibilities:

- plural-aware target addressing,
- common validation flow,
- canonical payload normalization,
- shared persistence orchestration skeleton.

Examples of derived responsibilities:

- translation source extraction and exact source identity rules,
- glossary partial-match metadata and preferred-variant rules,
- provider glossary compilation behavior,
- translation build/export integration.

### Relationship with current admin classes

`AdminPage` can remain a composition root, but it should stop being the place where resource behavior is decided.

The target split is:

- admin wiring in controller/facade classes,
- resource behavior in abstract and derived domain/editor classes,
- storage behavior in resource repositories,
- rendering in UI classes.

## Recommended JavaScript Design

### Why JS needs the same split

The current translation editor script is translation-specific and procedural.

If glossaries are added without a shared editor model, the repository will likely end up with:

- duplicated form serialization,
- duplicated status/provenance handling,
- duplicated filtering/search/edit logic,
- diverging UI behavior between translations and glossaries.

### Abstract JS layer

Introduce an abstract editor class mirroring the PHP domain split.

Recommended first layer:

1. `AbstractLinguisticResourceEditor`
   - bootstrapping from localized config,
   - row discovery,
   - payload serialization,
   - common filtering/search,
   - form change tracking,
   - save payload preparation,
   - common event wiring skeleton.

2. `AbstractLinguisticResourceRow`
   - row element access,
   - source/target field access,
   - status/meta access,
   - serialization hooks.

### Concrete JS classes

Recommended first concrete classes:

1. `TranslationEditor extends AbstractLinguisticResourceEditor`
2. `GlossaryEditor extends AbstractLinguisticResourceEditor`
3. `TranslationRow extends AbstractLinguisticResourceRow`
4. `GlossaryRow extends AbstractLinguisticResourceRow`

### JS implementation note

Because the current file is plain browser JavaScript without a build step, there are two acceptable implementation styles:

1. ES2015 classes, if the project accepts that syntax in admin assets.
2. Constructor functions plus prototypes, if strict continuity with the current script style is preferred.

The architectural requirement matters more than the syntax choice:

- one shared base editor abstraction,
- one derived translation editor,
- one derived glossary editor later.

## Target Storage Direction

### Principle

The database should become resource-centric instead of translation-centric.

Recommended conceptual tables:

1. `i18nly_linguistic_resources`
2. `i18nly_linguistic_resource_entries`
3. `i18nly_linguistic_resource_targets`
4. `i18nly_linguistic_resource_links`
5. `i18nly_linguistic_resource_compilations`

### Practical rule for this repository

Do not spend time designing migrations from the current translation tables.

Instead:

- create the new schema directly,
- delete and recreate local data as needed,
- keep the implementation simple,
- optimize for clarity of the new model rather than backward compatibility.

### Current-post anchor question

A WordPress post anchor can still be kept for translation resources because the admin workflow already depends on it.

Recommended practical rule:

- keep a WordPress post anchor where it simplifies permissions, menu integration, and edit-screen navigation,
- move business identity and business content to the new resource-centric tables.

Glossary resources may later use either:

- the same post-anchor strategy,
- or dedicated admin pages without an equivalent post anchor,

but that decision does not need to block the common model.

## Operational Refactoring Strategy

### Guiding rule

Do not attempt a big-bang rewrite.

Use small slices that progressively introduce the abstraction while keeping one clear next step at all times.

### Slice 0: Freeze naming and scope

Goal:

- agree on the generic names before code moves.

Deliverables:

- this note,
- `IA.md` as high-level architecture reference,
- chosen class naming conventions.

### Slice 1: Introduce PHP abstract classes without changing product behavior

Goal:

- create the shared PHP abstraction layer while keeping translations as the only implemented resource kind.

Deliverables:

- `AbstractLinguisticResource`,
- `TranslationResource`,
- first abstract editor/repository contracts,
- translation code adapted to instantiate the translation-derived classes.

Validation:

- existing translation tests stay green,
- no glossary behavior added yet.

### Slice 2: Introduce resource-centric schema and repositories

Status: done for the tables needed by translations (`linguistic_resources`, `_entries`, `_targets`). Translations own a `translation` resource row anchored on the post, targets reference it, and the legacy catalog/translated-entry aliases are removed. The `_links` and `_compilations` tables are deferred to Slice 6, when glossaries need them.

Goal:

- replace translation-centric business tables with the new resource-centric tables.

Repository-specific simplification:

- reset the database instead of writing migrations.

Deliverables:

- new schema manager,
- new repositories,
- translation persistence routed through the new tables,
- deletion of obsolete schema code when the new path is complete.

Validation:

- focused repository and persistence tests,
- manual translation creation/edit/save check if needed.

### Slice 3: Introduce JS editor abstraction for translations

Status: done. `AbstractLinguisticResourceEditor` and `AbstractLinguisticResourceRow` are implemented by `TranslationEditor` and `TranslationRow` (ES2015 classes, no build step, shared `window.I18nly` namespace, one script per class). Behavior is pinned by the jsdom suite in `tests/js`. `AbstractLinguisticResourceTarget` has no JS counterpart yet: targets are still the inputs of a row.

Goal:

- make the current translation editor the first concrete implementation of a generic resource editor.

Deliverables:

- abstract editor base,
- translation editor derived class,
- translation row abstraction,
- preserved current edit behavior.

Validation:

- existing JS/PHP integration behavior preserved,
- no glossary UI yet.

### Slice 4: Add first glossary resource backend

Status: done, without `GlossaryEditorModel` (it belongs to the UI slice). `GlossaryResource`, `GlossaryResourceEntry`, `GlossaryResourceTarget` and `GlossaryResourceRepository` are implemented, with a `GlossaryValidator` for the glossary-specific rules. The form index of glossary targets is the rank of the variant (0 preferred, 1+ alternates).

Goal:

- prove the abstraction with a second resource kind on the backend first.

Deliverables:

- `GlossaryResource`,
- `GlossaryResourceRepository`,
- minimal CRUD model,
- first glossary-specific validation rules.

Validation:

- focused repository and model tests,
- no need for complete UX yet.

### Hardening slices H1-H7 (from the audit)

Source: `docs/AUDIT.md` (2026-10-08). These slices come **before** slices 5 and 6: the audit found
security, data integrity, CI and product-completeness problems that slices 5 and 6 would inherit
(the glossary editor reuses the same save path, sanitisation and capabilities). Finding identifiers
(B1..B5, I1..I9) refer to the audit.

Recommended order: H1, H2, H3, H4, H5, H6, H7, then slices 5 and 6.

#### H1: Input validation and capabilities (audit B1, B2)

Status: done. `PluginSourceFiles::resolve_main_file()` rejects empty, `.`/`..`, NUL and `:` segments and checks with `realpath()` that the file stays under its root (symbolic links included); `TranslationSaveHandler` drops a source slug that is not an installed plugin and a language that is not a supported target language; the post type has its own capability type and every primitive capability maps to `manage_options` (`AdminRegistration::get_capabilities()`), which the `edit_post` checks of the AJAX handlers inherit. Tests: `PluginSourceFilesTest`, `TranslationSaveHandlerTest`, `AdminRegistrationTest`. Translations already saved with a bad slug are harmless thanks to the read-side check, but are not cleaned.

Goal:

- no user input can select a path or a user right outside the intended scope.

Deliverables:

- validate `source_slug` on save and in `PluginSourceFiles::resolve_main_file()` (must be a key of `get_plugins()` or, as a fallback, contain no `..` segment and stay under `WP_PLUGIN_DIR` after `realpath()`),
- validate the target language against the known locale list,
- register the CPT with its own `capability_type`/`capabilities` (for example `manage_options` based, mapped with `map_meta_cap`), and apply the same capability check to every AJAX handler,
- regression tests: traversal slugs rejected, a Contributor cannot create/edit a translation nor call the AJAX endpoints.

Validation: security tests green; manual check as Contributor, Author and Administrator.

#### H2: Raw storage of translations (audit B3)

Status: done. `Support\TranslationTextNormalizer` replaces `sanitize_text_field()`/`sanitize_textarea_field()` for translations, source texts sent to DeepL and the JSON payloads (it only removes invalid UTF-8 and control characters, and normalizes line breaks to `\n`). A double `wp_unslash()` of the entries payload, which corrupted backslashes and quotes, was removed. The PHPUnit stubs of `wp_unslash()` and `sanitize_text_field()` now behave like WordPress's, which is what had hidden these bugs. Output stays escaped (`esc_html` in the list table). Not done: size limits on payloads (see H6).

Goal:

- store exactly what the translator typed.

Deliverables:

- remove `sanitize_text_field()`/`sanitize_textarea_field()` from translation forms, source texts sent to DeepL and the entries payload; replace them with `wp_unslash()` + JSON validation + length limits + `wp_check_invalid_utf8()`,
- escape at output (already required by `esc_*`), sanitise only identifiers,
- tests: HTML, newlines, tabs, `%1$s`, trailing spaces and multibyte text round-trip unchanged through save, load and AI batch.

#### H3: CI green (audit B4, part of I7)

Status: code and metadata done, CI result to confirm. `FileLockThrottle::write_state()` carries the same justified `WordPress.WP.AlternativeFunctions` exemption as the rest of the flock-based class (the `fwrite` was the only call left outside it); `readme.txt` has `Stable tag` 0.1.1, `Tested up to` 7.1 (the value Plugin Check expects from the CI WordPress; the plugin has not been run on a real 7.1) and a refreshed description and changelog; `composer.json`/`composer.lock` require PHP 8.1 like the plugin header. `reuse lint` passes locally. Not done: JS tests in CI and phpstan (optional items below).

Deliverables:

- replace the direct `fwrite` in `Support/FileLockThrottle.php` (WP_Filesystem, or a justified and documented exception accepted by Plugin Check),
- align `Tested up to`, `Stable tag`, `Requires PHP` (header, `readme.txt`, `composer.json`) and refresh `readme.txt`,
- confirm the REUSE lint step runs again,
- optionally add the JS tests to the CI (see `tests/js/README.md`) and a phpstan baseline.

Validation: the CI workflow is green on `main`.

#### H4: Plugin lifecycle and schema migrations (audit I1, I2, I3)

Status: done. Activation creates the schema (`i18nly_activate()`); `SourceSchemaManager::maybe_upgrade()` now runs ordered, idempotent migration steps (`get_migration_steps()`, empty for now, the 0.4.0 schema being the first one with a migration path) and keeps the stored version when a step fails so that it is retried; `uninstall.php` and `Support\PluginUninstaller` remove translations, tables, options and throttle files, on every site of a network, only when the new setting "Delete all translations, glossaries and settings when the plugin is deleted" (Settings > Translations, off by default) was ticked; the DeepL key can be set with the `I18NLY_DEEPL_API_KEY` constant, which takes precedence and is never copied to the database. "Clear saved key" no longer erases the other settings. Not done: activation on a whole network only creates the tables of the activated site (the others create them on their first request).

Note: this revises the working assumption "No legacy migration" above. It is valid while there is no released version; it stops being valid at the first public release.

Deliverables:

- `uninstall.php` (custom tables, options, CPT posts, DeepL settings) with an opt-in setting to keep data,
- activation hook creating the schema, versioned migration runner based on `i18nly_source_schema_version`,
- allow the DeepL key to be supplied by a constant in `wp-config.php`.

#### H5: Export pipeline (audit B5)

Goal:

- produce usable PO, MO and JSON (JED) files from a translation.

Deliverables:

- decision (see `IA.md`, Third-Party Code): the vendored `third-party/wp-cli` copy is a reference, not a library, because it targets gettext 4 and WP-CLI classes while the plugin uses gettext 5; keep re-implementing on gettext 5 and port wp-cli behaviors case by case,
- before choosing, run the wp-cli test cases / sample sources through the `Build/` extractors and list the behavior gaps (feeds H7 extractor work),
- `MoExporter` on the gettext 5 `MoGenerator`; JSON (JED) exporter ported from `JedGenerator` (82 lines) with the `make-json` splitting rules by script; optional `.l10n.php` exporter ported from `PhpArrayGenerator`; plural forms taken from the plural data,
- download/save action on the edit screen, tests against files produced by `msgfmt`/WP-CLI when available.

##### H5 follow-up: removing the vendored wp-cli copy (if decided)

Remove it only once the behavior gaps found by the comparison are ported or consciously dropped. Checklist of everything that references it (inventory made on 2026-10-08):

Delete:

- `plugin/third-party/wp-cli/` (`src/`, `SYNC-LOG.md`, `upstream-info.json`, `index.php`),
- `scripts/sync-i18n-from-upstream.sh` (the update script; it only syncs this copy, nothing else calls it).

Edit:

- `plugin/REUSE.toml`: remove the `third-party/wp-cli/**` annotation (MIT, WP-CLI Contributors); keep the `third-party/vendor/**` one. Run the REUSE lint afterwards.
- `.vscode/psalm-plugin.xml`: remove the `../plugin/third-party/wp-cli` directory entry. `.vscode` is managed by codespaces-grafting: follow its rules (local override rather than editing a managed file, keep the `.orig` in step).
- `docs/IA.md`, section Third-Party Code: drop the reference-copy paragraph and the porting rule; keep a short note saying that the extractors were written on gettext 5 with wp-cli as inspiration (credit stays in the code comments of the ported parts).
- `docs/AUDIT.md` addendum and this document (H5 decision text): mark as done and point to the removal commit.
- Credits: if behavior or code is ported, keep the MIT notice of WP-CLI in the ported files (SPDX headers) and in `REUSE.toml` for those paths.

Nothing to change (verified, do not touch):

- Autoloader: there is no entry for the copy. `plugin/composer.json` only maps `WP_I18nly\\` to `includes/WP_I18nly/` and the vendor autoload does not list `WP_CLI\\I18n`; no `composer dump-autoload` is needed. If a classmap or PSR-4 entry is ever added for it, remove it too.
- `i18nly.php`, `phpunit`, `phpcs` (`*/third-party/*` is already excluded), the Plugin Check option `--exclude-directories=third-party` (still needed for `third-party/vendor`).
- WP-CLI as a development tool: the Dockerfile, bootstrap scripts, `.vscode/intelephense-stubs/wp-cli.php`, the `wpcheck` CI step and the optional locale filter of `scripts/generate-plural-specs.php` (see `scripts/plurals/README.md`) use the `wp` binary, not this copy.

Validation after removal: `phpcs`, PHPUnit and JS suites green, REUSE lint green, `grep -rI "third-party/wp-cli\|i18n-command" .` returns nothing outside the git history.

#### H6: Robustness and scale (audit I4, I5, I9)

Deliverables:

- optimistic concurrency (revision counter or `updated_at` check) on save,
- pagination instead of the hard 500-row cap, and batch loading instead of per-row queries,
- decision about restoring a trashed translation (see Known Limitations in `IA.md`).

#### H7: Quality backlog (audit I6, I8 and minor items)

Deliverables:

- internationalisation of the plugin JS (`wp_set_script_translations`) and a `languages/` directory,
- extractor gaps (fully qualified calls, typed TypeScript) with tests that check correctness, not only current output,
- finish slimming `AdminPage` (target under 400 lines), document the regeneration of `Plurals/Languages/Lang*.php`.

### Slice 5: Add first glossary editor UI

Prerequisites: H1 and H2 (the glossary save path must be authorised and must not alter terms).

Revised by the audit: the generic editor is translation-centric (source text, N target forms driven by plural data). A glossary has variants ranked by `form_index` and a `match_mode`. Do not force the generic editor to fit before the need is proven.

Slice 5a (first):

- a dedicated glossary admin screen on top of `GlossaryResourceRepository`: list, create, edit terms with ordered variants and `exact`/`partial` match mode,
- its own small `GlossaryEditor` JS class extending `AbstractLinguisticResourceEditor` only for what is really shared (payload, dirty state, save round-trip),
- `GlossaryEditorModel` (deferred from slice 4),
- capabilities and raw storage as defined by H1 and H2.

Slice 5b (only if duplication between the two editors proves real):

- factor the common row/target behavior upward and add the missing `AbstractLinguisticResourceTarget` JS counterpart.

Validation:

- glossary CRUD works, terms round-trip unchanged,
- translation UX remains stable (jsdom suite and golden tests green).

### Slice 6: Connect glossary resources to translations

Prerequisites: slice 5a and H5 (QA checks need the final strings and the export path).

Revised by the audit: DeepL glossaries support exact term pairs per language pair only. Scope accordingly.

Deliverables:

- resource links (the `_links` table, with a migration from H4),
- ordering rules,
- compilation model for provider sync limited to `exact` entries (`partial` entries are used locally only),
- QA usage of glossary resources in translation editing, computed locally (term present or absent in the translation, variant suggestions),
- DeepL glossary synchronisation as a separate, later sub-slice (6b) once local QA works.

Validation:

- linked glossary resolution is deterministic,
- translation editor can consume glossary-derived guidance,
- `partial` entries are never sent to DeepL.

## Recommended Deletions

To keep the refactoring honest, actively delete obsolete structures instead of keeping parallel dead models for long.

Candidates for deletion once replaced:

- translation-only storage code that duplicates resource-centric persistence,
- translation-only editor state assembly that becomes a special case of the generic editor model,
- any schema code retained only for hypothetical legacy migration.

## Non-Goals

This refactoring should not try to solve everything at once.

Explicit non-goals for the first phase:

- no full revision/history design,
- no provider-agnostic AI platform,
- no complete glossary sync UX from day one,
- no attempt to preserve a nonexistent legacy database.

## Key Design Decision Summary

1. Keep `Translation` and `Glossary` as product terms.
2. Use `Linguistic Resource` as the generic developer abstraction.
3. Implement the distinction mainly through abstract and derived classes in PHP and JS.
4. Prefer composition roots for wiring, not for core specialization logic.
5. Replace the current schema directly instead of designing migrations.
6. Validate the abstraction by making translations the first concrete resource and glossaries the second.
