<!--
SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later OR MIT
-->

# GitHub Workflows Variables — Setup Examples

This file contains **copy-paste ready** configurations for common scenarios.

You can set these values either in GitHub Variables or in `.devcontainer/.cs_env`.
GitHub Variables have priority over `.cs_env` when both define the same key.

## Scenario 1: WordPress Plugin in `plugins-src/my-plugin/`

Copy these into **Settings → Secrets and variables → Variables**:

```
WORKFLOWS_CI_ENABLED = true
WORKFLOWS_CI_BRANCHES = main,develop
WORKFLOWS_CI_PLUGIN_DIR = plugins-src/my-plugin
WORKFLOWS_CI_TESTS = phpunit,phpcs,wpcheck
WORKFLOWS_CI_PHP_VERSION = 8.2
WORKFLOWS_CI_PHPUNIT_CONFIG = phpunit.xml

WORKFLOWS_ZIP_ENABLED = true
WORKFLOWS_ZIP_BRANCHES = main
WORKFLOWS_ZIP_PLUGIN_DIR = plugins-src/my-plugin
WORKFLOWS_ZIP_EXCLUDE_PATTERNS = .git*,node_modules/*,.env*,tests/*,.github/*
WORKFLOWS_ZIP_ARTIFACT_RETENTION = 90
WORKFLOWS_ZIP_NIGHTLY_TAG = nightly
```

## Scenario 2: Multiple Plugins (Monorepo)

### First Plugin (`plugin-a`):

```
WORKFLOWS_CI_ENABLED = true
WORKFLOWS_CI_BRANCHES = main,develop
WORKFLOWS_CI_PLUGIN_DIR = plugins-src/plugin-a
WORKFLOWS_CI_TESTS = phpunit,phpcs

WORKFLOWS_ZIP_ENABLED = true
WORKFLOWS_ZIP_BRANCHES = main
WORKFLOWS_ZIP_PLUGIN_DIR = plugins-src/plugin-a
```

### For Second Plugin: Duplicate Workflows

1. Copy `.github/workflows/cs-grafting-ci.yml` → `.github/workflows/cs-grafting-ci-plugin-b.yml`
2. Copy `.github/workflows/cs-grafting-plugin-zip.yml` → `.github/workflows/cs-grafting-plugin-zip-b.yml`
3. Update runtime gate variables in the new files:

```yaml
# In ci-plugin-b.yml:
env:
  PLUGIN_DIR: plugins-src/plugin-b
  CI_ENABLED: ${{ vars.WORKFLOWS_CI_PLUGIN_B_ENABLED || 'true' }}
  TESTS_TO_RUN: ${{ vars.WORKFLOWS_CI_PLUGIN_B_TESTS || 'phpunit,phpcs' }}
```

Then add variables:

```
WORKFLOWS_CI_PLUGIN_B_ENABLED = true
WORKFLOWS_CI_PLUGIN_B_TESTS = phpunit,phpcs
```

## Scenario 3: Minimal (Only ZIP, No Tests)

```
WORKFLOWS_CI_ENABLED = false
WORKFLOWS_ZIP_ENABLED = true
WORKFLOWS_ZIP_BRANCHES = main,release/*
WORKFLOWS_ZIP_PLUGIN_DIR = plugins-src/hello-world
```

## Scenario 4: Development Mode (All Tests, No Release ZIPs)

```
WORKFLOWS_CI_ENABLED = true
WORKFLOWS_CI_BRANCHES = main,develop,feature/*
WORKFLOWS_CI_PLUGIN_DIR = plugins-src/my-plugin
WORKFLOWS_CI_TESTS = phpunit,phpcs,lint,wpcheck,reuse
WORKFLOWS_CI_PHP_VERSION = 8.3
WORKFLOWS_CI_PHPUNIT_CONFIG = tests/phpunit.xml.dist

WORKFLOWS_ZIP_ENABLED = false
```

## Scenario 5: Custom PHP Version & Extended Tests

```
WORKFLOWS_CI_ENABLED = true
WORKFLOWS_CI_BRANCHES = main
WORKFLOWS_CI_PLUGIN_DIR = plugins-src/my-plugin
WORKFLOWS_CI_TESTS = phpunit,phpcs,lint,wpcheck,reuse
WORKFLOWS_CI_PHP_VERSION = 8.3
WORKFLOWS_CI_PHPUNIT_CONFIG = phpunit.xml

WORKFLOWS_ZIP_ENABLED = true
WORKFLOWS_ZIP_BRANCHES = main,release/*
WORKFLOWS_ZIP_PLUGIN_DIR = plugins-src/my-plugin
WORKFLOWS_ZIP_EXCLUDE_PATTERNS = .git*,node_modules/*,.env*,tests/*,docs/*,*.lock
WORKFLOWS_ZIP_ARTIFACT_RETENTION = 180
```

## Scenario 6: Theme (Not Plugin)

```
WORKFLOWS_CI_ENABLED = true
WORKFLOWS_CI_BRANCHES = main
WORKFLOWS_CI_PLUGIN_DIR = themes/my-theme
WORKFLOWS_CI_TESTS = phpcs
WORKFLOWS_CI_PHP_VERSION = 8.2

WORKFLOWS_ZIP_ENABLED = true
WORKFLOWS_ZIP_BRANCHES = main
WORKFLOWS_ZIP_PLUGIN_DIR = themes/my-theme
WORKFLOWS_ZIP_EXCLUDE_PATTERNS = .git*,node_modules/*,.env*
```

---

## How to Apply

1. Go to your GitHub repository
2. **Settings** → **Secrets and variables** → **Variables**
3. Click **New repository variable** for each variable above
4. Run a test push or PR to trigger workflows
5. Check **Actions** tab to see results

---

## Verifying Configuration

After setting variables, make a test commit:

```bash
git commit --allow-empty -m "test: trigger workflows"
git push
```

Then check:
- **Actions** tab → see workflow runs
- Each workflow shows which variables it's using (in logs)

---

## Troubleshooting

### Workflows don't run

1. Check **Actions** tab for any error messages
2. Verify variable names are spelled correctly (case-sensitive)
3. Ensure PLUGIN_DIR path exists: `git ls-tree -r HEAD | grep -i plugins-src`
4. If CI_ENABLED or ZIP_ENABLED are `false`, jobs are intentionally skipped
5. If branch doesn't match `WORKFLOWS_*_BRANCHES`, jobs are intentionally skipped at runtime

### Tests fail

1. Verify plugin has required test files (phpunit.xml.dist, composer.json, etc.)
2. Check PHP version compatibility (some code may need 8.3+)
3. Review test output in Actions logs

### ZIP is empty or incomplete

1. Verify PLUGIN_DIR points to correct location
2. Check EXCLUDE_PATTERNS isn't excluding too much
3. Download artifact and inspect contents
4. Verify files aren't in .gitignore (they won't be zipped)

---

## Need Help?

- See [WORKFLOWS_CONFIG.md](WORKFLOWS_CONFIG.md) for detailed reference
- Check GitHub Actions docs: https://docs.github.com/en/actions
- Open an issue: https://github.com/evlist/codespaces-grafting/issues
