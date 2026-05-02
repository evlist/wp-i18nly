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

As verified in this repository on May 2, 2026:

- branch: `main`,
- current HEAD: `82c5923` — `Brainstorming around glossaries (aligning glossaries and translations)`,
- PHPUnit status: `OK (140 tests, 589 assertions)`,
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

- `i18nly_source_catalogs`,
- `i18nly_source_entries`,
- `i18nly_translated_entries`.

These tables currently hold:

- extracted source catalog metadata,
- extracted source entries,
- translated forms keyed by translation, source entry, and `form_index`.

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
- `AiTranslationManager`.

Current architectural assessment:

- this decomposition direction is valid,
- `AdminPage` still remains too broad and acts as a large composition/root class,
- reducing `AdminPage` to a thinner facade is still an open refactoring target.

For new code, keep responsibilities separated across:

- admin orchestration,
- UI rendering,
- business/storage services,
- technical support helpers.

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

## Open Items

The most meaningful current open items are:

1. finish reducing `AdminPage` to a thin composition facade,
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
