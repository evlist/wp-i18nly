<!-- SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com> -->
<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# I18nly — Session Context (AI Handover)

## 1) Project Purpose

**I18nly** is a WordPress plugin (work in progress) focused on simplifying i18n/l10n workflows.

The long-term product goal is to hide low-level translation file complexity from end users while keeping full compatibility with WordPress standards and tooling.

## 2) Current Repository State

- Main plugin folder: `plugin/`
- Main plugin file: `plugin/i18nly.php`
- Admin page class: `plugin/includes/class-i18nly-admin-page.php`
- Plugin index guard: `plugin/index.php`
- License text: `LICENSES/GPL-3.0-or-later.txt`
- Setup/overview doc: `README.md`

### Implemented so far

- A first working plugin bootstrap exists.
- A top-level admin sidebar menu entry (**Translations**) exists.
- Admin pages now follow WordPress patterns:
	- `All translations` (WP-style table),
	- `Add translation` (form),
	- `Edit translation` (details view).
- The `Add` flow creates a translation and redirects to the same `Edit translation` page used by list row links.

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

1. Add actions on `All translations` (for example trash/untrash) with minimal status handling.
2. Expand `Edit translation` page from read-only details to first editable translation entries.
3. Introduce dedicated entry storage (custom table) while keeping translation entity in CPT + meta.
4. Repeat with the same loop: implement → validate → commit.

## 11) POT Import Strategy Notes

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
