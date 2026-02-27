<!--
SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later OR MIT
-->

# GitHub Workflows Quick Start

## 60-Second Setup

### Step 1: Add Variables
Go to **Settings → Secrets and variables → Variables** and add:

```
WORKFLOWS_CI_PLUGIN_DIR = plugins-src/hello-world
WORKFLOWS_ZIP_PLUGIN_DIR = plugins-src/hello-world
```

Alternative: define the same keys in `.devcontainer/.cs_env`.

### Step 2: Done!
Push a commit. Workflows run automatically.

---

## Default Behavior (If You Add No Variables)

- ✅ **Workflow triggers** on every push and PR
- ✅ **CI job runs** only for branch `main` (runtime filter)
- ✅ **Tests**: PHPUnit + PHP CodeSniffer
- ✅ **PHP version**: 8.0
- ✅ **Plugin dir**: `plugins-src/hello-world`
- ✅ **ZIP workflow triggers** on every push; ZIP job runs only on `main` (runtime filter)
- ✅ **Nightly tag per branch** is updated with latest ZIP (e.g. `nightly-main`)

---

## Common Customizations

### Run CI on multiple branches
```
WORKFLOWS_CI_BRANCHES = main,develop,staging
```

You can also put this in `.devcontainer/.cs_env`.

### Only run PHP CodeSniffer (skip PHPUnit)
```
WORKFLOWS_CI_TESTS = phpcs
```

### Include WordPress Plugin Check
```
WORKFLOWS_CI_TESTS = phpunit,phpcs,wpcheck
```

### Include REUSE license lint
```
WORKFLOWS_CI_TESTS = phpunit,phpcs,reuse
```

### Disable CI, keep only ZIP builds
```
WORKFLOWS_CI_ENABLED = false
WORKFLOWS_ZIP_ENABLED = true
```

### Use PHP 8.3 instead of 8.0
```
WORKFLOWS_CI_PHP_VERSION = 8.3
```

### Custom plugin location
```
WORKFLOWS_CI_PLUGIN_DIR = src/plugins/my-plugin
WORKFLOWS_ZIP_PLUGIN_DIR = src/plugins/my-plugin
```

---

## Monitoring

1. Push a commit
2. Go to **Actions** tab
3. Click the workflow run to see details
4. Each step logs which config it's using

---

## Full Reference

See [WORKFLOWS_CONFIG.md](WORKFLOWS_CONFIG.md) for all options.
