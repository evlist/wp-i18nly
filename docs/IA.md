<!-- SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com> -->
<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# I18nly — Architecture and Current State

This document is the current handover note for the repository.

Its purpose is to describe:

- what is implemented today,
- which architectural decisions are still valid,
- which important constraints apply to new work,
- which directions are still intentionally open.

It should prefer verified current behavior over session history.

## Purpose

I18nly is a WordPress plugin focused on translation workflow management.

The product goal is to let users work with translations as first-class content objects while hiding most POT/PO/MO/JSON complexity behind a WordPress-native admin workflow.

## Verified Current State

As verified in this repository on October 8, 2026:

- branch: `main`,
- PHPUnit status: `OK (233 tests, 1095 assertions)`, plus 65 JavaScript tests (`tests/js`),
- runtime PHP code lives under `plugin/includes/WP_I18nly/`.

Current top-level runtime namespaces:

- `WP_I18nly\Admin`: admin controllers, settings, orchestration,
- `WP_I18nly\Admin\UI`: renderers, list-table helpers, edit-screen assets,
- `WP_I18nly\AI`: DeepL integration and usage status,
- `WP_I18nly\Build`: POT extraction, import, and temporary workspace generation,
- `WP_I18nly\Plurals`: plural-form registry and generated language specs,
- `WP_I18nly\Storage`: schema and `wpdb` repositories,
- `WP_I18nly\Support`: technical helpers and integration utilities.

## Current Product Model

### Primary user object

The primary user-facing object is still a **Translation**.

Current translation identity is based on:

- one source plugin slug,
- one target language,
- one WordPress admin post used as the translation anchor.

The source language is currently implicit and effectively treated as English in the AI plural-mapping logic.

### Current admin workflow

Implemented user flow:

1. open `All translations`,
2. create a translation from the `Add translation` screen,
3. edit the same translation in a dedicated edit screen,
4. save translated entries through the native post save flow.

Current edit-screen behavior includes:

- plugin selector and target-language selector at creation time,
- source and target language lock after creation,
- translation entries loaded into a dedicated editing table,
- filters by entry state, quality state, provenance, and text search,
- single-item and bulk AI translation actions,
- DeepL monthly usage visibility in admin.

There is no implemented glossary UI yet.

## Implemented Capabilities

### Translation administration

Implemented today:

- WordPress-native translation post type (`i18nly_translation`),
- list/add/edit admin screens,
- custom list columns and admin messages,
- translation edit controller and save handler,
- AJAX endpoint returning the translation entries table.

### Source extraction and persistence

Implemented today:

- extraction of source strings from plugin files,
- import of POT-derived source entries into custom tables,
- temporary POT workspace generation,
- persistence of translated entries in a dedicated table.

What is not implemented end-to-end yet:

- a completed save pipeline producing final MO and JSON artifacts as the canonical current workflow,
- a revision-aware translation history model.

### Translation entry editing

Implemented today:

- singular and plural entry editing,
- form-aware rendering using generated plural metadata,
- quality and provenance tracking,
- bulk actions on translation rows,
- search and row filtering in the edit table.

### DeepL integration

Implemented today:

- DeepL API key storage,
- connection testing from settings,
- single-item AI translation,
- multi-text batch translation,
- progress UI for bulk translation,
- server-side 429 handling with adaptive shared throttling,
- monthly usage gauge with cache,
- reserved monthly quota deduction,
- blocking of new sends when effective DeepL usage is above 100%.

### Plural handling

Implemented today:

- generated plural specs derived from a pinned GlotPress snapshot,
- PHP override layer for project-specific adjustments,
- runtime language classes under `WP_I18nly\Plurals\Languages`,
- registry and resolver services consumed by the edit UI and AI flow,
- audit support in the plural generation script.

## Current Storage Model

### Admin anchor

Translations are currently stored as WordPress posts of type `i18nly_translation`.

Current identity metadata is stored in post meta:

- `_i18nly_source_slug`,
- `_i18nly_target_language`.

### Business tables

The current canonical business tables are created by `SourceSchemaManager`:

- `i18nly_linguistic_resources`,
- `i18nly_linguistic_resource_entries`,
- `i18nly_linguistic_resource_targets`.

These tables currently hold:

- resource rows: one `source_catalog` row per extracted plugin catalog, one `translation` row per translation, and one `glossary` row per glossary, the latter anchored on the WordPress post through `anchor_post_id` (`0` for source catalogs); a translation resource is deleted with its targets when its post is permanently deleted, while a trashed translation keeps its data,
- source entries, which belong to a `source_catalog` resource,
- target values keyed by translation resource, source entry, and `form_index`.

The `i18nly_linguistic_resource_links` and `i18nly_linguistic_resource_compilations` tables from the refactoring plan are intentionally not created yet; they are only needed by the glossary slices.

In the editor model, `TranslationResource::get_resource_id()` is the storage resource ID and `get_translation_id()` is the anchoring post ID.

### Translation entry semantics

The current translated-entry model uses:

- `translation` for the target text,
- `status` for quality state,
- `used_ai` and `used_manual` for provenance,
- `form_index` for plural-aware alignment.

Current quality states are effectively centered on:

- `draft`,
- `suspect`,
- `validated`.

Legacy AI review tokens may still appear during normalization paths, but the persisted editing model is now quality/provenance-oriented rather than “AI state only”.

### Persistence policy

Important current rule:

- AI suggestions are not immediately persisted to the database,
- persistence happens through the translation save flow,
- save remains the point where translated entry state becomes canonical.

This is an important behavioral invariant and should be preserved unless deliberately redesigned.

## DeepL and AI Translation Behavior

### Current provider scope

AI translation is currently DeepL-only.

There is no generic multi-provider abstraction validated by multiple backends yet.

### Request model

Implemented today:

- single item requests,
- batch requests using one provider call per AJAX batch,
- client batch size and provider request limits controlled by PHP filters,
- sequential client execution by default.

Relevant existing configuration filters:

- `i18nly_ai_translate_batch_size`,
- `i18nly_ai_translate_max_items_per_request`,
- `i18nly_ai_translate_backoff_base_ms`,
- `i18nly_ai_translate_max_concurrent_batches`,
- `i18nly_ai_translate_min_delay_ms`.

### Safety rules

Current safety behavior includes:

- placeholder masking/restoration,
- non-blocking placeholder validation,
- review signaling instead of silent discard,
- preservation of plural form alignment.

### DeepL usage and quota handling

Current usage model:

- usage is fetched from DeepL and cached locally,
- cache is invalidated after successful translation batches,
- reserved monthly characters are deducted from the raw DeepL character limit,
- the settings page and admin UI expose usage status through a reusable gauge,
- new sends are blocked when effective usage exceeds 100%.

This quota-aware behavior is part of the implemented product, not just a design note.

### Server-side structure

- `TranslationAiAjaxHandler` handles the requests of the single and batch endpoints: parameters, capability, nonce, translation lookup, API key, quota guard, and the JSON answers.
- `TranslationBatchTranslator` translates one batch and knows nothing about HTTP: it prepares the texts, calls the provider once, derives the status of each translation, persists it and runs the throttle, rate limit and post-batch callbacks.
- The nonce verification stays inside each endpoint method, next to the request data it protects: the WordPress sniffs of the shared ruleset only accept it there, and the custom ruleset forbids `phpcs:ignore` comments in the plugin.
- The batch endpoint accepts the batch nonce and the single-entry nonce. The editor script sends the single-entry nonce for batches, so both must stay valid.

### Current plural heuristic in AI flow

The current AI plural mapping logic is intentionally limited:

- source locale is effectively treated as English,
- target form selection relies on generated witness examples,
- if the representative witness is `1`, use source singular,
- otherwise use source plural.

This is accepted as a scoped implementation constraint, not a generalized multilingual source strategy.

## Plural Specification Strategy

Plural data is intentionally handled as generated, auditable repository data rather than ad-hoc runtime knowledge.

Current source-of-truth model:

1. pinned GlotPress baseline snapshot in `scripts/plurals/upstream/`,
2. project override layer in `scripts/plurals/`,
3. generation script in `scripts/generate-plural-specs.php`,
4. generated runtime PHP classes in `plugin/includes/WP_I18nly/Plurals/Languages/`.

This strategy is implemented and should remain the reference model.

Important policy decisions:

- GlotPress is the baseline authority for this repository,
- WP locale listing is a scope filter, not the plural-rule authority,
- CLDR is intentionally not used as the primary runtime source in this pipeline.

## Admin Architecture Status

The admin area has already been substantially decomposed, but the decomposition is not fully complete.

Implemented extraction work includes dedicated collaborators such as:

- `TranslationEditController`,
- `TranslationAjaxController`,
- `TranslationSaveHandler`,
- `TranslationSettingsPage`,
- `TranslationMetaBoxRenderer`,
- `TranslationEntriesListTable`,
- `AiTranslationManager`,
- `TranslationFilterQuery` (entries filter query handling),
- `UI\EntryStatusBadges` and `UI\PluralFormPresenter` (badges and plural form metadata used by `TranslationEntriesListTable`),
- `DeepLUsageWidgets` (dashboard widget and edit-screen gauge),
- `DeepLUsageFactory` (single place building the DeepL usage status provider from saved settings),
- `TranslationDuplicateGuard` (duplicate translation detection),
- `LinguisticResources\TranslationEditorRowsProvider` (editor rows assembly).

Current architectural assessment:

- this decomposition direction is valid,
- `AdminPage` is now under the 700-line file limit (about 650 lines) but still above the 400-line recommendation and still acts as a large composition root,
- reducing `AdminPage` to a thinner facade is still an open refactoring target; the remaining weight is mostly public hook callbacks and protected factory methods that tests override.

For new code, keep responsibilities separated across:

- admin orchestration,
- UI rendering,
- business/storage services,
- technical support helpers.

## Front-end Architecture

The translation edit screen script is split into ES2015 classes under `plugin/assets/js/`, mirroring the PHP linguistic resource split. There is no build step: files share the `window.I18nly` namespace and are loaded as separate scripts, in the order of the manifest returned by `EditScreenAssets::get_script_definitions()`.

Generic layer, `linguistic-resource/` (independent of the resource kind):

- `AbstractLinguisticResourceEditor`: loads the entries table, keeps the hidden payload field of the post form in sync, applies filters, handles row selection, bulk actions and quality menus. Concrete editors implement `createRow()` and extend `getBulkActionHandlers()`, `bindRowControls()` and `onBoot()`.
- `AbstractLinguisticResourceRow`: one table row (inputs, quality and provenance tokens, filter matching, copy/clear/mark operations, payload serialization). Concrete rows implement `getKind()`.
- `EntryBadges`, `EntryFilterBar`, `ModifiedRowsTracker`, `AjaxClient`, `UiText`: stateless helpers and small collaborators.

Translation layer, `translation/`:

- `TranslationEditor` and `TranslationRow`: the first concrete editor and row.
- `AiBatchTranslation`, `AiErrorDialog`, `DeepLUsageGauge`: AI translation specifics.

`translation-edit.js` is only the entry point and keeps its handle and globals (`window.i18nlyTranslationEditConfig`, `window.i18nlyRebuildEntriesPayload`, `window.i18nlyPotInitDone`).

The JavaScript behavior is covered by jsdom tests in `tests/js` (see its README); the HTML fixtures they use are generated from the PHP renderers. They are not run by the CI yet; `tests/js/README.md` explains how to enable them without touching the files managed by the graft. A glossary editor will extend the same abstract classes.

## Build and Revision Status

### Build pipeline

The build namespace and supporting classes exist and are real:

- `PotGenerator`,
- `PotSourceImporter`,
- `PotSourceEntryExtractor`,
- `PotWorkspaceService`.

`PotSourceEntryExtractor` only orchestrates: it finds the source files and merges the entries found in several places (same context, string and plural) into one entry with all its references. The extraction itself is delegated by kind of source:

- `PluginSourceFiles`: main file resolution and file discovery (a directory plugin is scanned recursively, a root-level single-file plugin never triggers a scan of the plugins root),
- `PhpGettextExtractor`: PHP code (tokenizer) and Blade templates,
- `JsGettextExtractor`: JavaScript and JSX (Peast syntax tree) and the sources embedded in `.js.map` files,
- `JsonI18nExtractor`: `block.json`, `theme.json` and style variations,
- `GettextPlaceholders`: printf placeholder detection shared by the PHP and JS extractors.

What is implemented today is mainly:

- extraction,
- import,
- temporary POT generation.

What should not be overstated:

- a full authoritative “save translation -> build final artifacts” workflow is not yet the central implemented runtime path.

### Revision model

There is no dedicated implemented translation revision model yet.

Important current fact:

- translated content lives in business tables,
- it is not versioned through a dedicated immutable revision schema,
- WordPress revision-related UI strings may exist, but they do not imply a finished translation-history architecture.

Translation history remains an open architecture topic.

## Glossary and Linguistic Resource Direction

### Current implementation status

The glossary backend exists (slice 4 of the refactoring plan); there is no glossary UI, no matching or QA usage and no DeepL glossary synchronization yet.

What is implemented:

- `GlossaryResource`, `GlossaryResourceEntry` and `GlossaryResourceTarget` extend the abstract linguistic resource classes,
- `GlossaryResourceRepository` creates, lists, loads and deletes glossaries and saves and deletes terms; writes return a `GlossaryOperationResult` and term writes are transactional,
- `GlossaryValidator` holds the glossary rules.

Storage conventions of a glossary:

- identity: `resource_kind = 'glossary'`, `source_slug` is the glossary slug chosen by the user, plus the target locale; `anchor_post_id` is 0 as a glossary has no WordPress post; the slug and the locales cannot change after creation,
- a term is an entry row: `msgid` is the term, `msgctxt` is an empty string so that the unique key applies, `match_mode` is `exact` or `partial`, `translator_comment` holds the note,
- the translations of a term are target rows whose `form_index` is the rank of the variant, not a plural form: 0 is the preferred translation, 1 and above are the alternates,
- terms are unique per glossary ignoring case; the repository checks it, the database key is the safety net.

Not decided yet: a human readable glossary name (the slug is the only label), whether glossaries get a post anchor or dedicated admin pages, plural-aware terms.

### Preferred generic term

The preferred generic developer term is **linguistic resource**.

Recommended conceptual rule:

- `Translation` and `Glossary` remain user-facing product terms,
- `linguistic resource` is the generic internal abstraction,
- `translation` and `glossary` are resource kinds, not the root abstraction.

### Why this direction is preferred

The shared structural core is the same in both cases:

- a source-side collection of linguistic units,
- a target-side collection of aligned units,
- plural-aware mapping,
- usage-specific metadata.

The main difference is not the base row structure. It is how the resource is produced and used.

### Target architecture direction

The preferred future direction is:

- local canonical storage in dedicated business tables,
- one shared model for translations and glossaries,
- translation-specific and glossary-specific behavior layered on top,
- optional DeepL glossary synchronization for glossary-like compiled resources.

At the conceptual level, the shared model should cover:

- resources,
- entries,
- targets,
- links,
- compilations.

This is an architectural direction, not an implemented data model yet.

## Constraints and Working Rules

### Product and code constraints

- Full WordPress standards compliance is required.
- Full REUSE compliance with `GPL-3.0-or-later` is required.
- Comments and documentation must stay in English.
- Product logic should not assume shell execution at runtime when a PHP integration exists.

### Repository and environment constraints

- Managed `.devcontainer/` graft files should not be edited directly unless they are local override files.
- Runtime code should stay under the PSR-4 structure rooted at `plugin/includes/WP_I18nly/`.
- Vendored third-party code under `plugin/third-party/` should be treated as upstream-managed; durable fixes should prefer upstream or pinned forks over ad-hoc local divergence.

### Delivery discipline

This repository should continue to follow a small-slice XP workflow:

- tiny vertical slices,
- test-first when practical,
- focused validation before widening scope,
- behavior-oriented tests,
- deletion of stale scaffolding rather than speculative accumulation.

## Validation

Run these before pushing. The CI runs PHPUnit, the repository-wide phpcs check, `reuse lint` and Plugin Check (`WORKFLOWS_CI_TESTS` in `.devcontainer/.cs_env.d/30-i18nly.local.env`).

- **PHPUnit**: `phpunit` from the repository root (`phpunit.xml`). Expected: `OK`.
- **phpcs, as the CI runs it**: `phpcs --standard=.vscode/phpcs.xml .` from the repository root. Expected: no output and exit code 0. The ruleset sets `warning-severity` to 0, so only errors count, and it scans the tests as well as the plugin.
- **phpcs, with warnings**: the codespace alias `phpcs` adds `--warning-severity=1`. Running it on `plugin` only hides the errors of the test files, so run it on the whole repository. The warnings that remain are informative: files above the 400-line recommendation of the custom `FileLength` sniff (the hard limit that raises an error is 700 lines), the reserved parameter names `$resource` and `$default` in two abstract classes, and the direct `fwrite()` of `FileLockThrottle`.
- **JavaScript tests**: `cd tests/js && npm install && npm test`. They are not run by the CI yet; `tests/js/README.md` explains how to enable them.
- **REUSE**: `reuse lint`. If `tests/js/node_modules` exists locally it is listed as non-compliant although git ignores it; the CI checkout does not have it.

What the automated tests do not cover:

- **No real database.** The repository tests run on an in-memory double (`tests/phpunit/support/class-i18nly-test-inmemory-wpdb.php`). It emulates unique keys, defaults, transactions with rollback and the simple `SELECT` statements the repositories use; it approximates the case folding of a MySQL `*_ci` collation (no accent folding) and rejects any statement it does not know. SQL syntax, indexes and `dbDelta` behavior are therefore not verified against MySQL.
- **No real WordPress.** The admin screens, hooks and AJAX endpoints run against stubs. The editor script is tested in jsdom, and was checked once by hand in Chromium with a simulated `admin-ajax`, not in a full WordPress with DeepL.
- **Plugin Check** is only run by the CI.

## Known Limitations

### Restoring a trashed translation

Duplicate detection (`AdminPage::find_duplicate_translation_id()`) queries translation posts with `post_status => any`, which excludes the trash. This is deliberate: a trashed translation does not block creating a new one for the same source slug and target language.

Consequence:

- a trashed translation keeps its data (resource row and targets are only deleted when the post is permanently deleted),
- if a new translation was created for the same source slug and target language in the meantime, restoring the trashed one from the trash leaves two active translations for the same pair,
- both have their own resource row and their own targets, so no data is lost or mixed, but the pair is no longer unique from the user's point of view.

Possible fixes, not implemented:

- block the `trash_to_*` status transition when an active duplicate exists,
- or delete the translation resource on trash and accept that restoring starts from an empty translation.

Any decision should also settle whether a trashed translation should keep blocking or not.

### Source extraction

The extractor output is pinned by `PotSourceEntryExtractorGoldenTest`, limits included:

- fully qualified PHP calls such as `\__( 'text' )` or `\esc_html__( 'text' )` are not extracted (PHP 8 tokenizes them as a single name); calls after `use function __;` are,
- a TypeScript file containing type syntax cannot be parsed by Peast and yields no entry, silently; `.ts` files without type syntax and `.jsx` files work,
- a method call such as `$object->__( 'text' )` is extracted as if it were gettext,
- a Blade template is scanned twice, as a PHP file and after Blade compilation; the duplicates are merged,
- the two printf detection patterns of `GettextPlaceholders` overlap: the second one already matches everything the first one does.

### Schema changes and development databases

The schema version is stored in the `i18nly_source_schema_version` option and `dbDelta` runs when it changes. `dbDelta` adds missing columns and indexes but never drops or alters an existing one, and there is no migration (see the refactoring plan). After a version bump that changes a key, a development database must be reset (drop the `i18nly_linguistic_resource*` tables and delete the option). For instance, version 0.3.1 replaced the unique key `resource_identity` by `resource_scope`; a database created earlier keeps the old key, which blocks recreating a translation after its predecessor was trashed.

### Glossaries

- there is no human readable glossary name (the slug is the only label), no editor model, no UI, no import or export format (CSV, TBX...) and no DeepL glossary synchronization,
- terms are unique per glossary ignoring case; the repository checks it with PHP case folding, while the database key relies on the collation, so the two can disagree on accented characters,
- the glossary persistence has only been tested on the in-memory double (see Validation).

### Editor script quirks kept on purpose

These behaviors predate the refactoring of the script and are pinned by the jsdom tests:

- the hidden payload field is rebuilt on input events and on submit; after an AI translation, or after choosing a quality status on an empty field, it is only up to date again at the next input or at submit,
- the browser `alert()` fallback of the AI error dialog is unreachable while the message is not empty, which is always the case,
- the `suppressNotice` flag of the modified rows tracker has no observable effect after "Apply filters and close", because the tracked rows are emptied anyway.

## Third-Party Code

- `plugin/third-party/vendor` (Composer, loaded by `i18nly.php`): `gettext/gettext` 5.7 (`PoLoader`, `PoGenerator`, `Translations` are used in `Build/`; the MO loader/generators are available for the export), `mck89/peast` (JavaScript parser of `JsGettextExtractor`), and `gettext/languages` (transitive dependency of gettext, CLDR plural rules, deliberately unused: plural data comes from the GlotPress-based `Plurals` registry, see `scripts/plurals/README.md`, "Why GlotPress, Not CLDR?"; the specific gettext/languages limitations that ruled it out are to be recorded there).
- Division of work with gettext: gettext 5 reads and writes PO/POT (`PoLoader`, `PoGenerator`) and models entries (`Translations`, `Translation`, references, flags, comments); the plugin does not parse or write PO itself. gettext 5 core only provides the abstract `CodeScanner`: the PHP and JavaScript function scanners live in separate packages (`gettext/php-scanner`, `gettext/js-scanner`) that are not installed, so `PhpGettextExtractor` (`token_get_all`), `JsGettextExtractor` (Peast) and `JsonI18nExtractor` are the plugin's own. Evaluating those packages is part of the H5 comparison.
- `plugin/third-party/wp-cli/src` is a **reference copy** of `wp-cli/i18n-command` (see `SYNC-LOG.md`), not runtime code: nothing autoloads it. It targets `gettext/gettext` 4.x (`Gettext\Extractors\*`, `Gettext\Utils\ParsedComment`, `Gettext\Merge`) and WP-CLI classes (`WP_CLI`, `WP_CLI\Utils`, command classes), whereas the plugin depends on gettext 5.x, whose API is different (`Scanner`, `Loader`, `Generator`). Both gettext majors cannot be loaded together. The plugin therefore re-implements the extraction (`Build/` extractors) and uses the wp-cli sources as a behavior specification and a source of cases to test, not as a library.
- Porting rule: when a wp-cli behavior is wanted, port it to gettext 5 inside `Build/` (adapter style) and cover it with a test; do not call the vendored copy.

## Open Items

The project audit (`docs/AUDIT.md`) reorders the work. Hardening slices H1-H7 are defined in
`docs/linguistic-resource-refactoring.md` and come before the glossary UI:

1. H1 input validation and capabilities (source slug traversal, CPT capabilities),
2. H2 raw storage of translations (no `sanitize_text_field` on translations),
3. H3 CI green (Plugin Check, readme/version alignment, REUSE, optional JS tests in CI),
4. H4 uninstall, activation and schema migrations,
5. H5 PO/MO/JSON export pipeline and decision about the vendored wp-cli code,
6. H6 concurrency, pagination, restoring a trashed translation,
7. H7 quality backlog (JS internationalisation, extractor gaps, `AdminPage` under 400 lines, plural data regeneration),
8. slice 5a: dedicated glossary editor screen; slice 5b only if duplication is proven,
9. slice 6: glossary links, local QA and DeepL sync restricted to exact entries,
10. later: translation revision/history model, runtime observability for AI translation and throttling.

## Scope Rule for Future Updates

When updating this document:

- keep current behavior and current architecture separate from future design,
- remove historical session notes once they are superseded,
- avoid storing frozen commit histories or stale TODO inventories unless they still drive active work,
- prefer concise factual summaries over exhaustive brainstorming dumps.
