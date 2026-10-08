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
- PHPUnit status: `OK (172 tests, 804 assertions)`, plus 65 JavaScript tests (`tests/js`),
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

- resource rows: one `source_catalog` row per extracted plugin catalog, and one `translation` row per translation, the latter anchored on the WordPress post through `anchor_post_id` (`0` for source catalogs); a translation resource is deleted with its targets when its post is permanently deleted, while a trashed translation keeps its data,
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

The JavaScript behavior is covered by jsdom tests in `tests/js` (see its README); the HTML fixtures they use are generated from the PHP renderers. A glossary editor will extend the same abstract classes.

## Build and Revision Status

### Build pipeline

The build namespace and supporting classes exist and are real:

- `PotGenerator`,
- `PotSourceImporter`,
- `PotSourceEntryExtractor`,
- `PotWorkspaceService`.

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

Glossaries are not implemented yet as a first-class product feature.

The current repository only contains the architectural direction for that work.

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

## Open Items

The most meaningful current open items are:

1. finish reducing `AdminPage` to a thin composition facade (about 650 lines today, target under 400),
2. decide and implement the first glossary slice,
3. introduce the linguistic-resource refactoring only when it supports a concrete glossary/translation slice,
4. define a real translation revision/history model if revision browsing becomes product-critical,
5. clarify the long-term artifact build/save pipeline for final PO/MO/JSON generation,
6. optionally add better runtime observability for AI translation and throttling behavior.

## Scope Rule for Future Updates

When updating this document:

- keep current behavior and current architecture separate from future design,
- remove historical session notes once they are superseded,
- avoid storing frozen commit histories or stale TODO inventories unless they still drive active work,
- prefer concise factual summaries over exhaustive brainstorming dumps.
