<!-- SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com> -->
<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

<img src=".devcontainer/assets/icon.svg" width="64" height="64" alt="cs-grafting" />Codespace created with [evlist/codespaces-grafting](https://github.com/evlist/codespaces-grafting) -
[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=main&repo=evlist/wp-i18nly)

# ⚠️ I18nly (Work In Progress)

**I18nly** is a workflow management tool for WordPress internationalization (i18n). It centralizes and automates the detection, extraction, synchronization, and compilation of translation strings.

### 🎯 Objective
The primary goal of **I18nly** is to abstract the technical complexity of the standard WordPress translation pipeline. 

The traditional workflow—moving from **Source Code** to **.pot**, then **.po**, and finally to compiled **.mo** and **.json** files—is handled automatically. From a user or translator's perspective, the focus remains on the content. The management of specific file formats and naming conventions (such as MD5 hashes for JavaScript translations) is managed entirely by the plugin's internal logic.

### 🧬 Origins
This project is the successor to **[i18n-404-tools](https://github.com/evlist/wp-i18n-404-tools)**.

> [!NOTE]
> **On the name:** The "404" in the previous project referred to the "missing tools" in the WordPress ecosystem's i18n dashboard (the missing link), rather than missing files or server errors. **I18nly** builds upon that foundation, shifting from a diagnostic utility to a comprehensive workflow agent.

### 🛠️ Technical Features
* **System Independence:** Leverages `wp-cli/i18n-command` PHP classes natively. It does not require `shell_exec` or a global WP-CLI installation.
* **State Auditing:** Provides an immediate overview of the synchronization state between source code and localized files.
* **Automated Compilation:** Handles the generation of binary (`.mo`) and JavaScript-ready (`.json`) files seamlessly.
* **AI Integration:** Optional support for LLM APIs to provide context-aware translation suggestions.

### 📂 Repository Structure
* `plugin/`: The distributable WordPress plugin folder.
* `src/`: PSR-4 compliant business logic.
* **Dual Composer Setup:**
    * **Root:** Development tools (PHPCS, static analysis).
    * **Plugin-level:** Production dependencies (WP-CLI i18n components).

### 🚀 Current Implementation Status
The first implementation step is now in place:

* A WordPress plugin bootstrap file exists at `plugin/i18nly.php`.
* A top-level admin sidebar entry (**I18nly**) is registered.
* The menu opens an initial blank workspace page intended for future workflow UI.

### 🔧 Local Activation (Development)
1. Copy or symlink the `plugin/` directory into your WordPress `wp-content/plugins/` directory.
2. Activate **I18nly** in the WordPress admin Plugins screen.
3. Open **I18nly** from the left admin sidebar to access the starter workspace screen.

---
*I18nly — Streamlining WordPress Localization.*
