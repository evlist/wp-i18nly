<!-- SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com> -->
<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# I18nly — Session Context (AI Handover)

## 1) Project Purpose

**I18nly** is a WordPress plugin (work in progress) focused on simplifying i18n/l10n workflows.

The long-term product goal is to hide low-level translation file complexity from end users while keeping full compatibility with WordPress standards and tooling.

## 2) Current Repository State

- Main plugin folder: `plugin/`
- Main plugin file: `plugin/i18nly.php`
- Plugin index guard: `plugin/index.php`
- License text: `LICENSES/GPL-3.0-or-later.txt`
- Setup/overview doc: `README.md`
- Last commit: `bbc0ca2` — refactor statuses: persist provenance on save draft and fix quality/provenance badges
- Test suite: **117 tests, 455 assertions**, all green (PHPUnit 11.5)

### Namespace / file structure (current)

PSR-4 autoloader active. All runtime classes live under `plugin/includes/WP_I18nly/`.

| Namespace | Responsibility |
|-----------|---------------|
| `WP_I18nly\Admin` | Admin controllers, AJAX handlers, settings |
| `WP_I18nly\Admin\UI` | Renderers, list tables, view helpers |
| `WP_I18nly\AI` | DeepL client, credentials validator, AJAX handler |
| `WP_I18nly\Build` | POT pipeline (generator, importer, extractor, workspace service) |
| `WP_I18nly\Plurals` | Plural forms registry |
| `WP_I18nly\Storage` | Schema manager, wpdb repository, temporary storage |
| `WP_I18nly\Support` | Technical utilities (file-lock throttle, etc.) |

### Implemented so far

- Plugin bootstrap, WordPress CPT-based translation entity, admin sidebar menu.
- `All translations` (WP-style list table), `Add translation` (form), `Edit translation` (details + entry editing).
- The `Add` flow creates a translation and redirects to the same `Edit translation` page used by list row links.
- Per-entry translation status model with AI badge (`ai_draft_ok`, `needs_review_*`, `human_verified`, `human_edited`).
- Per-form status badges for plural entries; hidden when translation is empty.
- POT source import pipeline (extract → persist entries in custom table).
- DeepL API-key based AI translation:
  - Single-item and batch AJAX translation flows.
  - Placeholder masking/restoration strategy (witness-based for single printf token).
  - Plural form mapping heuristic (EN source → form index by witness value).
  - Settings page with API key storage and connection test.
- AI bulk translation UI:
  - Progress modal (centered overlay, cancel/close buttons, fill bar).
	- Sequential batch execution (one batch at a time, configurable batch size, default 50).
	- No client-side artificial delay between successful requests.
  - DeepL 429 detection with `Retry-After` header parsing propagated through all layers.
	- Server-side adaptive throttle shared by all concurrent clients (file lock based).
	- Adaptive delay policy: use `Retry-After` when present, otherwise double the delay; TTL = `max(30 min, 10 × delay)`.

## 3) UX/Product Direction Agreed in Session

The core user object should be a **Translation** entity.

A Translation is identified by:

- object type (plugin first; theme/core later),
- source language (English first),
- target language.

Expected workspace actions:

- open,
- close,
- save,
- reload,
- switch via tabs between opened translations.

Guiding UX principle: users manipulate translations, not raw POT/PO/MO/JSON internals.

## 4) Technical Direction Agreed in Session

### Interaction model

- Use AJAX endpoints for fluid UX in the admin workspace.
- Keep operations asynchronous for expensive jobs (extract/build/compare).

### Storage model

- Translation entities are currently stored as a custom post type (`i18nly_translation`).
- Translation identity fields are stored in post meta:
	- `_i18nly_source_slug`,
	- `_i18nly_target_language`.
- Entry-level data is expected to move to a dedicated custom table later when editing workflows become richer.

### Build pipeline intent

- On reload/open refresh: extract source strings and regenerate POT context when needed.
- On save: chain generation of POT, MO, and JSON artifacts.
- Use WP-CLI i18n and gettext classes (no shell dependency assumption in product logic).

### Localization of I18nly itself

- The plugin UI itself must be localizable.
- JS translations must use WordPress JSON translation files.

## 5) Compliance Constraints

- Full WordPress standards compliance is required.
- Full REUSE compliance with `GPL-3.0-or-later` is required.
- Comments and documentation must be in English.

### Practical note from this session

For this repository setup, SPDX metadata in PHP files can be placed in file docblocks (example style already applied in plugin PHP files).

## 6) Devcontainer / Graft Rules (Important)

This repository uses `evlist/codespaces-grafting` for Codespace provisioning and workflow scaffolding.
This context is important when interpreting `.devcontainer/` behavior and CI/ZIP workflow conventions.

Do **not** customize managed files in `.devcontainer/` unless they are local override files.

- Avoid modifying managed files such as `.devcontainer/.cs_env` directly.
- Put custom env variables in `.devcontainer/.cs_env.d/[priority]-[name].local.env`.

Active local override file created in this session:

- `.devcontainer/.cs_env.d/30-i18nly.local.env`

Current intent of that file:

- local plugin linking (`PLUGIN_SLUG=i18nly`, `PLUGIN_DIR=plugin`),
- optional extra local plugin install (`WP_PLUGINS=plugin-check`),
- workflow runtime defaults for CI/ZIP targeting `plugin/`.

## 7) CI/Workflow Notes

Repository includes graft workflows:

- `.github/workflows/cs-grafting-ci.yml`
- `.github/workflows/cs-grafting-plugin-zip.yml`

Configured workflow path target should be `plugin` for this project.

Current selected CI suites in local override are oriented to standards/licensing checks (WPCS + REUSE), with PHP 8.3.

## 8) XP Principles (Team Rule)

Implementation should now follow an explicit **Extreme Programming (XP)** workflow.

Core principles for this repository:

1. **Tiny vertical slices only**
	- implement one very small user-visible increment at a time,
	- avoid speculative architecture or schema expansion.

2. **Strict test-first (Red → Green → Refactor)**
	- start by writing or strengthening one failing test that expresses the expected behavior,
	- run tests before implementation and record the failing state (**Red**),
	- implement the minimal code change to pass (**Green**),
	- run a refactoring pass at constant behavior, then rerun tests (**Refactor**).

3. **Behavior-oriented tests over implementation mirroring**
	- prefer assertions on observable business outcomes/invariants,
	- avoid tests that only mirror internal implementation details,
	- include idempotence/regression checks when relevant to reduce shared reasoning errors.

4. **Validate continuously**
	- run focused checks first (lint/unit), then standards checks,
	- do not defer validation to the end of a long sequence.

5. **One step, one commit**
	- each completed slice should be committed independently,
	- commit messages should describe one behavior change only.

6. **Prefer deletion over speculation**
	- remove temporary scaffolding that is not part of the current slice,
	- keep docs and code aligned with what is actually implemented.

7. **Session reporting discipline**
	- explicitly report slice status as **Red**, **Green**, then **Refactor** before commit,
	- if a step is skipped, state why and treat it as an exception.

## 9) Next Steps (XP Order)

Items 1–4 from the previous list are now done or superseded. Current open items:

1. **Expose `translateMaxRetryAttempts` via PHP filter**:
   Add `get_translate_max_retry_attempts()` in `AiTranslationManager`, include it in `extend_script_config()`,
   add assertion in `AdminPageRenderTest`.

2. **Plural forms data source strategy** (see section 17): implement generator script and runtime artifact.

3. **Continue Slice 3 admin decomposition** until `WP_I18nly\Admin\AdminPage` is a thin facade.

4. **Optional observability slice (recommended)**:
	Add lightweight telemetry counters for AI translation runtime (`429` count, current adaptive throttle delay,
	average batch size, and average request duration) to make production behavior visible over time.

5. Repeat with the same loop: implement → validate → commit.

## 10) Implemented Slice: Quality/Provenance Decoupling (Current Behavior)

This slice is now implemented end-to-end in backend, payload handling, and UI rendering.

### Persistence policy

- **No immediate DB persistence** on AI AJAX translation anymore.
- Persistence happens on post save flow only (`Save draft` / update submit).
- On save, each translated form persists:
	- translated text,
	- quality status (`draft`, `suspect`, `validated`),
	- provenance flags (`used_ai`, `used_manual`).

### Data model semantics

- `status` represents **quality only**.
- `used_ai` and `used_manual` represent **provenance only**.
- Legacy tokens are normalized to current quality tokens:
	- `draft_ai`, `ai_draft_ok` -> `draft`
	- legacy suspect variants -> `suspect`

### UI badge rules (Edit screen)

- Status column displays badges in this order:
	1. quality badge,
	2. provenance badges,
	3. obsolete badge (if source entry is obsolete).
- Quality and provenance are visually distinct concepts, but `AI` and `Manual` provenance chips intentionally share the same visual style.

### Dynamic interaction rules

- After AI translation succeeds for a form:
	- add/show `AI` provenance chip,
	- remove `Manual` provenance chip,
	- update quality badge from returned review token.
- After a user manually edits a non-empty field:
	- ensure `Manual` provenance chip is present,
	- keep `AI` chip if already present,
	- set quality badge to `Draft`.
- When a field is cleared (empty translation):
	- remove both `AI` and `Manual` provenance chips,
	- clear effective quality status for persistence.

### Plural forms alignment rule

- Clearing one plural form must not shift badges for subsequent forms.
- A non-semantic placeholder quality badge is kept in the emptied line only for layout stability.
- The placeholder token is converted back to empty status in the payload before persistence.

### Asset cache-busting note

- Plugin asset version was bumped to `0.1.1` to force admin JS/CSS refresh and avoid stale badge behavior from browser cache.

## 11) Psalm Compatibility & Usage

### Compatibility Issues

Psalm is used for static analysis and dead code detection. Recent versions (Psalm 6.x) require PHP >=8.3.16, which may not match the devcontainer or CI environment. Additionally, global Composer installs can conflict with other tools (e.g., PHPUnit 11), making it impractical to install Psalm globally alongside other dependencies.

### Installation in Temporary Directory

To avoid conflicts, install Psalm in a dedicated directory (outside Composer global):

## 12) Translation Revision Strategy (Architecture Note)

### Problem statement

The project currently uses a WordPress custom post type (`i18nly_translation`) as the native admin container for a translation, while the actual translation content is stored in custom tables (`source_entries` and `translated_entries`).

This model is strong for querying, filtering, plural-form handling, provenance tracking, and incremental updates, but it does not automatically benefit from the native WordPress revision workflow because the translated content does not live in `wp_posts.post_content`.

The main design question is therefore how to support translation history and revision browsing while preserving the current data model.

### Option A: Make `post_content` the canonical translation storage

Possible serialized formats:

- JSON snapshot of the full translation.
- `.po`-like textual serialization.

Advantages:

- maximal reuse of native WordPress revisions, autosaves, and post lifecycle;
- old revisions are naturally stored and browsable through WordPress mechanisms;
- one canonical payload can be serialized/deserialized with a well-understood format;
- `.po` is a meaningful candidate because the product already needs `.po` parsing and serialization support.

Disadvantages:

- weak fit for the current domain model (entry-level status, provenance, comments, plural-form indexing, partial updates);
- harder and slower querying/filtering compared to normalized tables;
- larger and more fragile save payloads for big catalogs;
- higher coupling between editor behavior and one serialized document format.

Assessment:

- technically viable;
- attractive if strict reuse of WordPress post/revision behavior becomes the primary goal;
- less attractive if translation editing remains a structured, query-heavy workflow.

### Option B: Keep current business tables and mirror full snapshots into WordPress revisions

In this model, business tables remain canonical, but each save also stores a full snapshot into the translation post (for example JSON or `.po`) so that WordPress revisions can be created and reused.

Advantages:

- preserves the current normalized storage model;
- allows native WordPress revision IDs to represent global translation versions;
- makes `.po` snapshots possible without abandoning the business schema.

Disadvantages:

- browsing a historical version still requires reconstructing a view model from the snapshot rather than reading the live tables directly;
- the snapshot and the tables must stay consistent;
- multi-user editing remains more complex because the canonical state is still outside the post revision model.

Assessment:

- good hybrid approach if the project wants revision integration quickly;
- still introduces duplication and synchronization concerns.

### Option C: Keep business tables canonical and implement dedicated translation revisions

In this model, translation history is stored in dedicated immutable business tables. The WordPress post remains the admin anchor, but translation history is modeled explicitly at the domain level.

Advantages:

- best fit for structured translation data;
- supports efficient querying and filtering on the current state;
- allows reliable historical browsing without destructive rehydration;
- naturally supports optimistic concurrency checks for multi-user editing.

Disadvantages:

- requires a dedicated revision design rather than relying only on core WordPress behavior;
- more implementation work than simply serializing into `post_content`.

Assessment:

- strongest architectural fit if translation editing becomes collaborative and history-aware.

### Refined recommendation discussed in session

The current preferred direction is a hybrid centered on native WordPress revision IDs as the global revision anchor, while keeping translation data in business tables.

Important constraint:

- a simple association between a WordPress revision ID and the current mutable rows in `translated_entries` is not sufficient, because those rows are updated in place and would no longer represent the historical state later.

Recommended structure:

- keep `translated_entries` as the current working state;
- when WordPress creates a revision on global translation save (`Save draft` / update), capture the corresponding WordPress revision ID;
- create immutable snapshots of translated entry rows for that revision;
- store those immutable snapshots in a dedicated revision-oriented table;
- optionally add a separate mapping table only if version deduplication is later needed.

Practical recommendation for the first implementation:

- use the WordPress revision post ID as the global translation revision identifier;
- keep one current-state table for editing and querying;
- add one immutable table for translated entry versions keyed by `wp_revision_id`;
- do not start with a many-to-many mapping table unless deduplication becomes necessary.

### Why this is currently preferred

- It preserves the existing structured storage model.
- It makes historical browsing possible without mutating live data.
- It keeps the WordPress revision workflow relevant and visible to users.
- It leaves room for later `.po` snapshot export/import if needed.
- It provides a clean foundation for future conflict detection in collaborative editing.

### Notes about `.po` as a serialized format

During the discussion, `.pot` was mentioned first, but the intended format was `.po`.

That correction matters:

- `.pot` is a source template format and is not the right format for storing translated content;
- `.po` is semantically valid for translated data and is already aligned with required parsing/serialization capabilities of the project.

Current conclusion:

- `.po` remains a valid snapshot/export format candidate;
- it is not currently the preferred canonical storage format for live editing data;
- it may still be useful as a revision snapshot format or interchange format later.

```bash
mkdir -p /home/vscode/.local/psalm5
cd /home/vscode/.local/psalm5
composer require --dev vimeo/psalm:^5
```

This keeps Psalm isolated and avoids dependency issues.

### Usage Instructions

To run Psalm with the workspace-local config and stubs:

```bash
php /home/vscode/.local/psalm5/vendor/bin/psalm --config=.vscode/psalm-plugin.xml --output-format=compact
```

For dead code detection:

```bash
php /home/vscode/.local/psalm5/vendor/bin/psalm --config=.vscode/psalm-plugin.xml --output-format=json --find-unused-code
```

The config and stubs are located in `.vscode/`. Stubs are enriched to reduce false positives from WordPress dynamic hooks.

## 12) POT Import Strategy Notes

Planned import logic must eventually reconcile three potential inputs:

- source POT data,
- optional PO data shipped with the plugin,
- entries already persisted in the database.

Expected runtime scenarios include plugin upgrades with or without attached PO updates.

Current MVP slice scope is intentionally narrower:

- import and persist source entries from POT only,
- postpone PO merge/conflict policies to later slices,
- keep POT header persistence separate from translation post-meta when shared across multiple translations.

### TODO (future slice)

- Add a dedicated settings page to manage editable POT header defaults
	(for example `Language-Team`, `Last-Translator`, contact values),
	while keeping template-specific placeholders when appropriate.

## 12) Third-Party Dependency Governance Note

Current project constraints include vendored third-party sources under `plugin/third-party/`
that may be overwritten by update scripts.

For security/compliance fixes affecting vendored upstream code, prefer this durable path:

1. maintain fixes in upstream-friendly branches/forks,
2. propose fixes as upstream PRs,
3. consume pinned fork references when upstream is not yet released,
4. rebase/merge upstream regularly to reduce long-term drift.

Avoid relying on ad-hoc local edits in vendored code as a permanent strategy.

## 13) Potential TODOs

### Evaluate `wp_mock` for unit tests

Potential improvement: reduce custom WordPress stubs in PHPUnit bootstrap by using `wp_mock` for selected unit tests.

Constraints for this repository:

- do not add test tooling to `plugin/composer.json`,
- install in a dedicated external Composer directory (similar to isolated Psalm setup),
- document setup so local and CI usage stay reproducible.

Suggested spike scope:

1. migrate one high-mock test class,
2. compare readability/maintenance cost vs current bootstrap stubs,
3. decide whether to generalize progressively.

## 14) PSR-4 Autoload Management

The plugin runtime now uses a single Composer PSR-4 autoloader.

### Current Runtime Model

- runtime autoload file: `plugin/third-party/vendor/autoload.php`,
- namespace root: `WP_I18nly\\`,
- namespace path: `plugin/includes/WP_I18nly/`,
- legacy custom `spl_autoload_register` loader has been removed,

- classmap fallback has been removed.

### Composer Configuration

`plugin/composer.json` uses:

```json
"autoload": {
	"psr-4": {
		"WP_I18nly\\": "includes/WP_I18nly/"
	}
}
```

Runtime dependencies remain in `plugin/third-party/vendor` (`vendor-dir` override).

### Operational Commands

After class/file moves or namespace changes:

```bash
composer dump-autoload --working-dir=plugin
phpunit
```

Recommended focused checks:

```bash
php -l plugin/includes/WP_I18nly/<ClassName>.php
phpcs --standard=.vscode/phpcs.xml plugin/includes/WP_I18nly/<ClassName>.php
```

### Contribution Rules For New Classes

- place new runtime classes under `plugin/includes/WP_I18nly/`,
- use `namespace WP_I18nly\\...;` with explicit sub-namespaces by responsibility,
- prefer domain-oriented namespaces (`Admin`, `Admin\\UI`, `Support`, `Build`) over generic buckets,
- use PSR-4 class/file naming (for example `FooBar.php` for `WP_I18nly\\FooBar`),
- avoid reintroducing legacy `I18nly_*` class names for runtime code,
- keep tests updated to require or reference the namespaced classes.

## AI Translation Integration (API Key, DeepL-first)

### Product Decision

The next AI translation increment targets **API-key based integration** with a
single provider first: **DeepL API**.

Rationale:

- low implementation risk for a first production-capable slice,
- good cost predictability for small/medium plugin translation volumes,
- clear path to incremental generalization after one stable provider is live.

This is intentionally **not** a super-set abstraction for all providers yet.

### Scope (V1)

- user enters a DeepL API key in plugin settings,
- plugin validates key/connectivity via a lightweight endpoint call,
- plugin can request translations for one entry first, then small batches,
- translations are returned into existing translation entry UI.

### Translation Quality State (Review Token)

For AI-assisted output, do not use a binary accept/reject model.

Use a persisted review token with explicit states so AI output can remain visible
while still surfacing risk and review needs.

Initial state vocabulary:

- `ai_draft_ok`: generated and technical checks passed,
- `needs_review_placeholders`: placeholder integrity issue detected,
- `needs_review_plural_mapping`: plural mapping used heuristic path,
- `needs_review_ambiguity`: low-context or low-confidence wording,
- `human_verified`: validated by a human reviewer,
- `human_edited`: modified by human after AI suggestion.

Design rule:

- never silently discard AI output,
- always attach state and reason flags,
- keep human review as the final authority.

Out of scope for V1:

- multi-provider orchestration,
- routing/fallback between providers,
- advanced prompt templates for generic LLMs,
- background queue/workers.

### Domain Contract (DeepL-first)

Introduce one application-facing service contract dedicated to current needs
without over-abstracting:

- `translate_item(...)` for one singular/plural form,
- `translate_batch(...)` for selected rows/forms,
- `validate_credentials(...)` for settings page test,
- `estimate_characters(...)` for user-facing cost transparency.

The request payload should carry:

- source locale,
- target locale,
- source text,
- entry identifier and form index,
- optional context (`msgctxt`) when available.

Recommended context additions for better MT quality:

- translator comment when available,
- UI/runtime usage hint (for example admin notice/error),
- placeholder guidance metadata.

The result payload should carry:

- translated text,
- item identifiers to map back to UI rows,
- provider warnings/errors in a normalized shape.

It should also carry:

- review state token,
- machine-readable validation flags.

### Placeholder Safety Policy

Placeholder checks must be strict but non-blocking for UX:

1. mask placeholders before external translation call,
2. restore placeholders after translation,
3. validate count/order/types,
4. if mismatch: keep suggestion but mark `needs_review_placeholders`.

This policy replaces hard rejection for initial slices.

### Deterministic Placeholder Strategy (Current Implementation)

For reliability, current implementation uses a deterministic fallback in addition
to provider context hints:

1. only activate when exactly one printf placeholder exists (`%s` or `%d`,
	including positional forms like `%1$s`),
2. use the plural-form witness `n` selected from generated examples,
3. replace the placeholder with witness `n` before translation,
4. skip this replacement when the same witness value already exists in source
	text (to avoid ambiguous reverse replacement),
5. translate,
6. restore placeholder by replacing the first standalone witness occurrence in
	translated text.

Scope limitations (intentional for V1):

- no substitution when multiple placeholders are present,
- no substitution when witness value is unavailable.

DeepL `context` is still sent as a secondary hint, but correctness for this
slice should not rely on context alone.

### Plural Strategy (Current Constraint: Source Locale Is English)

Current simplification accepted for V1:

- source locale is fixed to English,
- source has two forms (`msgid` singular and `msgid_plural` plural).

For each target plural form:

1. take the first representative example value for that target form,
2. if the example value is `1`, use source singular form,
3. otherwise, use source plural form,
4. request translation for that target form using this mapped source form,
5. store result with review metadata.

This heuristic is intentionally scoped to EN→* and should be generalized later
if source locale becomes configurable.

### Plural Metadata Generation Requirements

Generated plural classes should expose structured metadata usable by the AI
translation flow, not only human-readable tooltips.

In addition to labels/markers/tooltips, generation should provide:

- per-target-form representative examples as arrays,
- deterministic form indexing contract for runtime use.

These arrays are the runtime input for the EN source-form mapping heuristic.

### XP Delivery Plan (Small Vertical Slices)

1. ✅ **Settings + key validation**
	- Secure storage for DeepL key.
	- "Test connection" action.

2. ✅ **Single-item translation action**
	- Translate one selected form from edit screen.
	- Write result back to the corresponding input only.

3. ✅ **Plural-aware handling**
	- Form indexes stable for plural entries.
	- Each target plural form generated using EN-source heuristic (`n=1` → source singular, otherwise source plural).
	- No cross-form overwrite.

4. ✅ **Small batch translation (bulk action)**
	- Progress modal with cancel button and fill bar.
	- Sequential batch execution (one lot at a time).
	- Per-item success/error report; review state token attached.

5. ✅ **DeepL 429 handling and backoff**
	- `DeepLClient` detects HTTP 429, reads `Retry-After` header (integer seconds or HTTP-date format).
	- `TranslationAiAjaxHandler` propagates as HTTP 429 JSON response with `retry_after_ms`.
	- `FileLockThrottle` applies one shared server-side delay across all concurrent clients/processes.
	- Adaptive delay policy: use `Retry-After` when present, otherwise double current delay.
	- Adaptive delay is persisted with TTL: `max(30 min, 10 × delay)`, then automatically reset.
	- JS retries sequentially with server-provided `retry_after_ms`, without client-side exponential logic.

6. ✅ **Safety checks**
	- Placeholder integrity (`%s`, `%d`, etc.) — witness-based masking/restoration.
	- HTML/tag preservation in place.
	- Unsafe outputs flagged via review token (no hard reject in V1).

7. ✅ **Cost visibility** — estimated source-character volume display.
	*(Note: batch now defaults to 50 strings/lot aligned to DeepL API max.)*

8. ✅ **Multi-text DeepL batch**:
	`DeepLClient::translate_batch(array $items)` now sends one HTTP request for one JS lot.
	`TranslationAiAjaxHandler` uses one batch provider call per AJAX batch (single item = batch size 1).

### Configuration values (PHP filters + JS config)

| PHP filter | Default | Purpose |
|---|---|---|
| `i18nly_ai_translate_batch_size` | 50 (= max items) | JS client lot size |
| `i18nly_ai_translate_max_items_per_request` | 50 (capped) | DeepL API max texts/call |
| `i18nly_ai_translate_backoff_base_ms` | 1000 ms | Legacy value (client exponential backoff removed) |
| `i18nly_ai_translate_max_concurrent_batches` | 1 | Client parallelism (keep at 1) |
| `i18nly_ai_translate_min_delay_ms` | 250 ms | Server-side floor before adaptive delay (FileLockThrottle) |

### Key files (AI layer)

| File | Role |
|---|---|
| `plugin/includes/WP_I18nly/AI/DeepLClient.php` | HTTP client, one-request multi-text batch translation, 429/Retry-After parsing |
| `plugin/includes/WP_I18nly/AI/TranslationAiAjaxHandler.php` | Unified single/batch pipeline, one provider call per batch, 429 propagation |
| `plugin/includes/WP_I18nly/Admin/AiTranslationManager.php` | Config values, handler wiring, adaptive throttle update callback |
| `plugin/includes/WP_I18nly/Support/FileLockThrottle.php` | Shared file-lock throttle with adaptive delay persistence and TTL reset |
| `plugin/assets/js/translation-edit.js` | Sequential batch UI loop, server-driven retry waits |
| `plugin/assets/css/translation-edit.css` | Progress modal styles |

### UX and Compliance Constraints

- Keep API key optional: no mandatory AI onboarding.
- Do not store keys in source control or export artifacts.
- Keep all comments/docs in English.
- Keep WordPress standards and REUSE compliance.

### Future Generalization Path

After DeepL V1 is stable, generalize by extracting a provider-agnostic
interface from real usage points (not from speculation), then add a second
provider to validate abstraction quality.

## 15) Slice 3 Decomposition Direction (Admin)

Primary architectural concern identified after PSR-4 migration: current `AdminPage` and `AdminPageHelper` remain too broad and mix UI orchestration with technical utilities.

### Current status (March 2026)

Slice 3 is **substantially advanced** but not fully closed.

Implemented so far:

- edit-screen behavior extracted into `WP_I18nly\Admin\TranslationEditController`,
- admin orchestration classes: `AdminPage`, `TranslationAjaxController`, `TranslationSaveHandler`, `TranslationSettingsPage`,
- UI collaborators under `WP_I18nly\Admin\UI`: `TranslationMessages`, `TranslationListColumns`, `EditScreenAssets`, `TranslationMetaBoxRenderer`, `TranslationEntriesListTable`,
- technical collaborators under `WP_I18nly\Support` (including `FileLockThrottle`),
- storage collaborators under `WP_I18nly\Storage` (`SourceSchemaManager`, `SourceWpdbRepository`, `TemporaryStorage`),
- plural rules under `WP_I18nly\Plurals\PluralFormsRegistry`,
- AI layer under `WP_I18nly\AI` (`DeepLClient`, `DeepLCredentialsValidator`, `TranslationAiAjaxHandler`),
- `AiTranslationManager` in `WP_I18nly\Admin` (config + AJAX wiring for AI translation).

Still pending:

- `WP_I18nly\Admin\AdminPage` remains a large multi-responsibility class,
- admin orchestration, UI wiring, and technical glue are still partially concentrated in that facade,
- therefore Slice 3 should be tracked as ongoing until `AdminPage` is reduced to thin composition/root wiring.

### Target decomposition

- split edit-screen behavior (`post-new.php` and `post.php`) into a dedicated component (for example `TranslationEditController`),
- keep high-level admin hook wiring in a thin admin controller/facade,
- separate UI/rendering responsibilities from technical helpers.

### Suggested namespace structure

- `WP_I18nly\\Admin\\...` for admin controllers,
- `WP_I18nly\\Admin\\UI\\...` for renderers/list table/view helpers,
- `WP_I18nly\\Support\\...` (or `Infrastructure`) for technical utilities and integration details.

`AdminPage` specifically belongs in an admin/application namespace (facade/controller role), **not** in `Admin\\UI`.

### Incremental migration strategy

1. extract translation edit flow from `AdminPage` into a dedicated class,
2. move pure UI helpers out of `AdminPageHelper` into `Admin\\UI` classes,
3. move technical utility methods into support/infrastructure classes,
4. keep behavior stable with tests at each extraction step.

This direction is intended to reduce static coupling, improve readability, and prepare upcoming admin features (including future settings pages).

## 16) Build Namespace Direction (POT Pipeline)

To avoid a catch-all `Utils` namespace, POT pipeline classes have been moved toward an explicit build-oriented namespace.

Implemented target:

- `WP_I18nly\\Build\\PotGenerator`,
- `WP_I18nly\\Build\\PotSourceImporter`,
- `WP_I18nly\\Build\\PotSourceEntryExtractor`,
- `WP_I18nly\\Build\\PotWorkspaceService`.

Rationale:

- these classes express a domain workflow (artifact build/import),
- `Build` is more explicit and maintainable than generic `Utils`,
- it aligns with the same responsibility-first namespace strategy used for `Admin` and `Support`.

Migration pattern used successfully (small XP slices):

1. move one class,
2. update imports/usages/tests,
3. run focused checks (`php -l`, `phpunit`),
4. commit,
5. repeat.

## 17) Plural Forms Data Source Strategy (Source Of Truth)

### Problem Statement

Current `PluralFormsRegistry` readability is impacted by mixed concerns:

- language rules,
- UI defaults (markers/tooltips),
- fallback and normalization logic.

This makes targeted changes (for example changing bullets/markers for one language) harder than necessary and reduces traceability to public references.

### Transparency Requirement

Plural data must be reproducible from documented public sources, not from implicit model knowledge.

Reference direction agreed in session:

- use a pinned GlotPress locales snapshot as canonical language baseline,
- keep I18nly-specific UI choices (markers/tooltips/overrides) explicit,
- document provenance and generation steps.

### Preferred Architecture

Use a two-layer source model plus a build step:

1. **Public baseline data** (GlotPress locales snapshot with pinned source URL).
2. **Project overrides in PHP** (rule-based transforms, not static-only key/value).
3. **Generator script** in `scripts/` merges baseline + overrides.
4. **Generated runtime artifact** in plugin code is PHP (autoload/OPcache-friendly).

This keeps runtime simple and fast while preserving maintainable and auditable source inputs.

### Why PHP Overrides (instead of JSON-only overrides)

A PHP override layer enables expressive rules without building a custom DSL:

- language-specific custom tooltips,
- rule-based markers for language groups (for example all locales with `nplurals > 2`),
- conditional transforms based on normalized spec.

Maintainers of this plugin are expected to know PHP already, so this increases practical flexibility with low cognitive overhead.

### Alternative Options Considered

1. **Single JSON for all locales**
	- pros: easy diffing and schema validation,
	- cons: less expressive for conditional rules.

2. **One JSON file per locale**
	- pros: simple targeted edits,
	- cons: many files and weaker support for global conditional policies.

3. **Database source**
	- pros: dynamic updates,
	- cons: unnecessary runtime complexity for mostly static linguistic rules.

4. **Runtime cache layer**
	- useful as optimization,
	- not a substitute for a clear and auditable source-of-truth model.

### Suggested Repository Shape

- `scripts/plurals/` for source baseline + PHP overrides + generator,
- `plugin/includes/WP_I18nly/Plurals/` for generated runtime map and registry reader,
- `docs/` references to GlotPress snapshot version and provenance.

### Governance Rules For This Strategy

1. Treat baseline GlotPress snapshot and override code as authoritative inputs.
2. Keep generated plugin artifacts deterministic and reproducible.
3. Require tests (golden/regression) around generation output for key locales.
4. Document every baseline update with source version and changelog notes.

### WordPress / Gettext Scope Clarification

To avoid ambiguity about "who is authoritative" for plural rules:

1. **GlotPress locales are the baseline authority for this project** (version-pinned snapshot in repository).
2. **WP-CLI locale listing is a scope filter** (which locales are relevant to WordPress), not a rule authority.
3. **gettext/WP-CLI i18n stacks are consumers** of `Plural-Forms` metadata (header parsing, PO/MO/JSON generation), not canonical rule sources for all locales.

Practical consequence for generation pipeline:

- derive baseline specs from pinned GlotPress locale data,
- constrain output set with WP locale coverage,
- enforce deterministic checks with strict audit before regenerating runtime classes.

### Why Not CLDR In This Pipeline

CLDR is intentionally not used as source of truth here because:

1. GlotPress is directly curated and used by WordPress translation infrastructure.
2. GlotPress locale metadata already provides gettext-compatible `nplurals` and `plural_expression` values.
3. Using CLDR in addition to GlotPress adds ingestion and reconciliation complexity with no functional gain for the plugin runtime.

### Audit Requirement (Fail-Fast)

Plural generation now supports an explicit audit gate in script usage:

- command-level option: `--audit`,
- optional report artifact: `--audit-report=build/plurals-audit.json`,
- stricter policy toggle: `--audit-fail-on-overrides`.

Current audit checks target high-risk drift points:

1. unresolved or non-gettext-compatible `plural_expression` values,
2. `nplurals` versus `forms` count inconsistencies,
3. mismatch against curated known gettext expressions,
4. optional hard failure whenever project overrides are applied.

This gives a deterministic review surface and prevents silent regressions from ad-hoc fixes.

### Current Upstream Baseline Pin (March 2026)

- Canonical source file: `locales.php` in GlotPress SVN (`plugins.svn.wordpress.org/glotpress/trunk/locales/locales.php`).
- Current pinned snapshot in repository: `scripts/plurals/upstream/glotpress-locales.php`.
- License for imported source data: **GPL-2.0-or-later**.

## 18) Current Codebase Status (March 2026)

### Git state

- Branch: `main`
- Last commit: `401f9bf` — refactor ai batch/throttle: unify single+batch flow with adaptive shared delay
- Working tree: clean (no uncommitted changes)

### Test suite

- **117 tests, 451 assertions**, all green (PHPUnit 11.5.55, PHP 8.3.6)

### Recent commit history (most relevant)

| Hash | Message |
|------|---------|
| `401f9bf` | refactor ai batch/throttle: unify single+batch flow with adaptive shared delay |
| `cb24671` | AI bulk translation: keep adaptive inter-batch throttle across retries |
| `161f253` | AI bulk translation: DeepL policy defaults and robust 429 backoff |
| `7e2d11d` | AI bulk translation: batch AJAX flow with server-side throttling |
| `359e9e5` | Hide status badges for empty translations |
| `a417f53` | Per-form status badges for plural translations |
| `0c0813f` | Plural witnesses: prefer singular when examples include 1 |

### Open items (not yet started)

- **Expose `translateMaxRetryAttempts` via PHP filter** — add `get_translate_max_retry_attempts()` in `AiTranslationManager`, propagate to JS config, add test.
- **AdminPage thin-facade remaining work** — `Slice 3` still technically open.
- **Plural forms generator script** — strategy defined in section 17 but not yet implemented.
- **Optional telemetry slice** — counters for `429`, adaptive throttle delay, request duration, and effective batch size.

## 10) Session Safety Checklist for Future Runs

Before editing:

- verify current branch and changed files,
- keep edits minimal and focused,
- avoid changing managed graft files,
- preserve WordPress coding standards,
- preserve REUSE licensing metadata.

Before finishing:

- run PHP lint,
- run PHPCS with repository ruleset,
- summarize functional impact and next steps.
