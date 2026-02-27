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
- A top-level admin sidebar menu entry (**I18nly**) exists.
- The admin page currently exposes a minimal blank workspace container.

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

- Use database tables for unsaved translation versions/drafts.
- On open: load DB draft if available.
- Run extraction/refresh checks against source files to detect changes.

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

## 8) What to Do Next (Suggested Priority)

1. Define MVP data schema for Translation entities and draft storage tables.
2. Define AJAX contract (endpoints, payloads, status model, nonce/capability checks).
3. Implement workspace skeleton UI with tabs and translation lifecycle actions.
4. Implement async job orchestration for extract/reload/save pipeline.
5. Add build/export integration (POT/PO/MO/JSON) using WP i18n/gettext classes.
6. Add admin-visible job status/logging and robust error reporting.

## 8.1) Testing Strategy (Agreed)

Unit tests should be added incrementally during implementation (not postponed to the end).

Goal:

- catch regressions early,
- keep behavior stable while refactoring,
- ensure each new feature ships with corresponding test coverage.

Practical approach:

- add tests next to each new service or workflow step,
- prioritize deterministic tests for business rules and state transitions,
- run focused tests first, then broader checks (PHPCS/CI suites).

Note on front-end testing:

- add JavaScript unit tests when/if front-end logic becomes significantly stateful or complex.

## 9) Session Safety Checklist for Future Runs

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
