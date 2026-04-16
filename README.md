<!-- SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com> -->
<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

<img src=".devcontainer/assets/icon.svg" width="64" height="64" alt="cs-grafting" />Codespace created with [evlist/codespaces-grafting](https://github.com/evlist/codespaces-grafting) -
[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=main&repo=evlist/wp-i18nly)

# ⚠️ I18nly (Work In Progress)

**I18nly** is a WordPress plugin focused on translation workflow management. It aims to let users work with translations as first-class content objects while keeping compatibility with WordPress i18n tooling and file formats.

### 🎯 Objective
The primary goal of **I18nly** is to abstract the technical complexity of the standard WordPress translation pipeline.

The traditional workflow moving from source code to `.pot`, then to translated entries and finally to compiled `.mo` and WordPress JSON artifacts, is intended to stay behind the scenes. From a user or translator perspective, the focus should remain on the translation itself rather than on gettext internals, file naming conventions, or build steps.

### 🧬 Origins
This project is the successor to **[i18n-404-tools](https://github.com/evlist/wp-i18n-404-tools)**.

> [!NOTE]
> **On the name:** The "404" in the previous project referred to the "missing tools" in the WordPress ecosystem's i18n dashboard (the missing link), rather than missing files or server errors. **I18nly** builds upon that foundation, shifting from a diagnostic utility to a comprehensive workflow agent.

### 🛠️ Technical Features
* **WordPress-native admin workflow:** translations are managed in the admin as dedicated entities, with list, add, and edit screens.
* **POT import pipeline:** source strings can be extracted and persisted for editing inside the plugin.
* **Plural-aware editing:** plural form metadata is resolved from generated locale specs derived from GlotPress data.
* **DeepL integration:** optional API key based AI translation is available for single items and batch translation.
* **Adaptive throttling:** HTTP 429 handling includes shared backoff and `Retry-After` support for sequential batch execution.
* **No shell dependency in product logic:** runtime behavior relies on PHP integrations rather than `shell_exec`.

### 📂 Repository Structure
* `plugin/`: The distributable WordPress plugin folder.
* `plugin/includes/WP_I18nly/`: PSR-4 runtime code.
* `tests/phpunit/`: PHPUnit coverage for the current implementation.
* `scripts/`: build and generation scripts, including plural spec generation.
* `docs/`: project notes and implementation context.

### 🚀 Current Implementation Status
The project is in active development.

Current implemented slices include:

* translation admin pages and list table,
* source entry import and storage,
* plural form registry backed by generated locale classes,
* DeepL settings and connection testing,
* single-item and batch AI translation flows,
* quality and provenance tracking for translated entries.

The architecture is still evolving, especially around admin decomposition and later build/revision flows.

### 🔧 Local Activation (Development)
1. Copy or symlink the `plugin/` directory into your WordPress `wp-content/plugins/` directory.
2. Activate **I18nly** in the WordPress admin Plugins screen.
3. Open the top-level `Translations` menu to access the translation list, creation flow, and edit screen, then use `Settings > Translations` for the DeepL settings page.

### ✅ Validation Status
The current PHPUnit suite is green: 124 tests, 531 assertions.

---
*I18nly — Because translating a plugin in WordPress should be as simple as writing a blog post.*
