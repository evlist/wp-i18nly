<!--
SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later OR MIT
-->

# GitHub Workflows Configuration Guide

## Overview

The workflows in `.github/workflows/` are configurable via **GitHub repository variables** and `.devcontainer/.cs_env` (runtime merge). You can control:

1. **Which workflows run** (enable/disable CI, ZIP building)
2. **Which branches are allowed to run jobs** (runtime filter: main, develop, staging, etc.)
3. **Which tests execute** (phpunit, phpcs, lint, wpcheck, reuse, etc.)
4. **Plugin directory location** (for monorepos or custom structures)
5. **Artifact exclusions** (what files to exclude from ZIP builds)

---

## Configuration Variables

Configuration precedence for runtime options:

1. **GitHub Variables** (Settings → Secrets and variables → Variables)
2. **`.devcontainer/.cs_env` + `.devcontainer/.cs_env.d/*`** (merged at workflow runtime)
3. **Workflow defaults** (hardcoded fallback)

This precedence applies to all `WORKFLOWS_*` variables consumed at runtime in `check-enabled` jobs (not only `*_ENABLED` / `*_BRANCHES`).

### CI Workflow (`cs-grafting-ci.yml`)

Runtime-resolved variables in this workflow:
- `WORKFLOWS_CI_ENABLED`
- `WORKFLOWS_CI_BRANCHES`
- `WORKFLOWS_CI_PLUGIN_DIR`
- `WORKFLOWS_CI_TESTS`
- `WORKFLOWS_CI_PHP_VERSION`

| Variable | Default | Purpose | Example |
|----------|---------|---------|---------|
| `WORKFLOWS_CI_ENABLED` | `true` | Enable/disable CI workflow | `true`, `false` |
| `WORKFLOWS_CI_BRANCHES` | `main` | Comma-separated branches allowed to run CI job (runtime filter) | `main,develop,staging` |
| `WORKFLOWS_CI_PLUGIN_DIR` | `plugins-src/hello-world` | Path to plugin directory | `plugins-src/my-plugin` |
| `WORKFLOWS_CI_TESTS` | `phpunit,phpcs` | Comma-separated test suites to run | `phpunit`, `phpcs`, `lint`, `wpcheck`, `reuse` |
| `WORKFLOWS_CI_PHP_VERSION` | `8.0` | PHP version for tests | `8.0`, `8.1`, `8.2`, `8.3` |

### ZIP Build Workflow (`cs-grafting-plugin-zip.yml`)

Runtime-resolved variables in this workflow:
- `WORKFLOWS_ZIP_ENABLED`
- `WORKFLOWS_ZIP_BRANCHES`
- `WORKFLOWS_ZIP_PLUGIN_DIR`
- `WORKFLOWS_ZIP_EXCLUDE_PATTERNS`
- `WORKFLOWS_ZIP_ARTIFACT_RETENTION`
- `WORKFLOWS_ZIP_NIGHTLY_TAG`

| Variable | Default | Purpose | Example |
|----------|---------|---------|---------|
| `WORKFLOWS_ZIP_ENABLED` | `true` | Enable/disable ZIP building | `true`, `false` |
| `WORKFLOWS_ZIP_BRANCHES` | `main` | Comma-separated branches allowed to run ZIP job (runtime filter for push events) | `main,release/*` |
| `WORKFLOWS_ZIP_PLUGIN_DIR` | `plugins-src/hello-world` | Path to plugin to ZIP | `plugins-src/my-plugin` |
| `WORKFLOWS_ZIP_EXCLUDE_PATTERNS` | `.git*,node_modules/*,.env*,tests/*` | Files to exclude from ZIP | `.git*,node_modules/*,dist/*` |
| `WORKFLOWS_ZIP_ARTIFACT_RETENTION` | `90` | Artifact retention (days) | `30`, `60`, `90` |
| `WORKFLOWS_ZIP_NIGHTLY_TAG` | `nightly` | Base prefix for nightly release tags (branch suffix added automatically) | `nightly`, `latest` |

---

## How to Configure

### Option A: GitHub Variables

### Step 1: Go to Repository Settings

1. Navigate to your GitHub repository
2. Go to **Settings** → **Secrets and variables** → **Variables**
3. Click **New repository variable**

### Step 2: Add Variables

Add each variable you want to customize. Leave defaults unchanged if you don't need to customize them.

#### Example: Simple Setup (WordPress Plugin)

```
Variable Name: WORKFLOWS_CI_ENABLED
Value: true

Variable Name: WORKFLOWS_CI_BRANCHES
Value: main,develop

Variable Name: WORKFLOWS_CI_PLUGIN_DIR
Value: plugins-src/hello-world

Variable Name: WORKFLOWS_CI_TESTS
Value: phpunit,phpcs
```

#### Example: Monorepo Setup

If you have multiple plugins:

```
Variable Name: WORKFLOWS_CI_PLUGIN_DIR
Value: plugins-src/my-first-plugin

Variable Name: WORKFLOWS_ZIP_PLUGIN_DIR
Value: plugins-src/my-first-plugin

# Later: duplicate and change plugin dir for additional plugins
```

#### Example: Disable Tests

To run only PHP CodeSniffer (phpcs) and skip PHPUnit:

```
Variable Name: WORKFLOWS_CI_TESTS
Value: phpcs
```

To disable CI entirely:

```
Variable Name: WORKFLOWS_CI_ENABLED
Value: false
```

### Option B: `.cs_env` (shared with Codespaces)

You can define runtime workflow variables in `.devcontainer/.cs_env` (or `.devcontainer/.cs_env.d/*`).

Example:

```dotenv
WORKFLOWS_CI_ENABLED=true
WORKFLOWS_CI_BRANCHES=main,develop
WORKFLOWS_CI_PLUGIN_DIR=plugins-src/hello-world

WORKFLOWS_ZIP_ENABLED=true
WORKFLOWS_ZIP_BRANCHES=main,release/*
WORKFLOWS_ZIP_PLUGIN_DIR=plugins-src/hello-world
```

If the same variable exists in both places, GitHub Variables take precedence.

---

## Workflow Behavior

### CI Workflow (`cs-grafting-ci.yml`)

**Triggers:**
- On every push
- On pull requests

**Runtime gate:**
- Job runs only if `WORKFLOWS_CI_ENABLED == true`
- Job runs only if current branch matches `WORKFLOWS_CI_BRANCHES`

**Steps:**
1. ✓ Verifies plugin directory exists
2. ✓ Sets up PHP with configured version
3. ✓ Installs WP-CLI (only when `wpcheck` is enabled)
4. ✓ Installs Composer dependencies (if composer.json exists)
5. ✓ Runs enabled tests:
    - **phpunit**: Runs PHPUnit tests (prefers `phpunit.xml`, then `phpunit.xml.dist`)
   - **phpcs**: Runs PHP CodeSniffer with WordPress standard
    - **wpcheck**: Runs WordPress.org Plugin Check via WP-CLI (`wp plugin check`)
    - **reuse**: Runs license compliance checks (`reuse lint`)
   - **lint**: Runs custom `scripts/lint.sh` (if exists)

### ZIP Build Workflow (`cs-grafting-plugin-zip.yml`)

**Triggers:**
- On every push
- On published GitHub Release

**Runtime gate:**
- Job runs only if `WORKFLOWS_ZIP_ENABLED == true`
- For `push` events, branch must match `WORKFLOWS_ZIP_BRANCHES`
- `release` events are allowed regardless of branch filter

**Steps:**
1. ✓ Verifies plugin directory exists
2. ✓ Creates ZIP artifact excluding configured patterns
    - ZIP filename and archive root folder use `PLUGIN_SLUG` (else plugin directory basename)
3. ✓ Uploads to GitHub Actions artifacts (transient, ~90 days)
4. ✓ Recreates nightly pre-release using branch-specific tag `${WORKFLOWS_ZIP_NIGHTLY_TAG}-<branch-slug>` (ensures tag, source archives, and assets match latest push)
5. ✓ Uploads to release assets (on published release)

---

## Advanced: Custom Test Scripts

If you want to run custom tests, add a `scripts/lint.sh` in your plugin directory:

```bash
#!/bin/bash
# plugins-src/hello-world/scripts/lint.sh

set -e

echo "Running custom linting..."
# Your custom checks here
```

Then enable it:

```
Variable Name: WORKFLOWS_CI_TESTS
Value: phpunit,phpcs,lint
```

---

## Troubleshooting

### Workflow triggered but job skipped

**Cause:** `WORKFLOWS_*_ENABLED=false` or branch does not match `WORKFLOWS_*_BRANCHES`.

**Solution:** Set `WORKFLOWS_*_ENABLED=true` and verify branch patterns in variables and/or `.cs_env`.

### Tests fail with "plugin directory not found"

**Cause:** `WORKFLOWS_CI_PLUGIN_DIR` is set to a non-existent path.

**Solution:** Verify the path matches your actual plugin location. Run `git ls-tree -r HEAD` to list all files.

### ZIP contains wrong files

**Cause:** `WORKFLOWS_ZIP_EXCLUDE_PATTERNS` might be excluding too much, or plugin structure is unexpected.

**Solution:** Download the artifact and inspect contents. Update patterns if needed.

### Tests skip silently

**Cause:** Test files/tools don't exist (e.g., no `phpunit.xml`/`phpunit.xml.dist`, no `scripts/lint.sh`, or missing prerequisites for `wpcheck`).

**Solution:** Workflows print warnings (⚠) when test files are missing. Create them or disable those tests.

---

## Best Practices

1. **Use branch protection**: Enable status checks for these workflows on `main` branch.
2. **Separate CI and ZIP branches**: Run CI on all branches, but ZIP only on `main` and `release/*`.
3. **Document plugin structure**: Keep a `docs/SETUP.md` explaining where your plugin lives.
4. **Version control variables**: Consider keeping workflow configs in a `.github/workflows.config` document.

---

## Example: Complete Multi-Plugin Monorepo

If you have multiple plugins in `plugins-src/`:

```
plugins-src/
├── plugin-a/
│   ├── plugin-a.php
│   └── composer.json
└── plugin-b/
    ├── plugin-b.php
    └── composer.json
```

Set per-workflow:

```
# For plugin-a tests
WORKFLOWS_CI_PLUGIN_DIR = plugins-src/plugin-a
WORKFLOWS_CI_TESTS = phpunit,phpcs

# For plugin-b ZIP
WORKFLOWS_ZIP_PLUGIN_DIR = plugins-src/plugin-b
WORKFLOWS_ZIP_EXCLUDE_PATTERNS = .git*,node_modules/*,tests/*,composer.lock
```

Or duplicate workflows as `ci-plugin-a.yml`, `ci-plugin-b.yml`, etc. for independent control.

---

## References

- GitHub Variables documentation: https://docs.github.com/en/actions/learn-github-actions/variables
- Workflow syntax: https://docs.github.com/en/actions/using-workflows/workflow-syntax-for-github-actions
- Issue #16: GitHub Workflows Support
