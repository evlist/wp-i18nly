<!-- SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com> -->
<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# JavaScript tests

Tests for the admin scripts under `plugin/assets/js/`. They run in [jsdom](https://github.com/jsdom/jsdom) with the Node.js built-in test runner; no browser and no build step are needed.

```sh
cd tests/js
npm install
npm test
```

Node.js 20 or later is required. These tests are not part of the PHP CI workflows.

## How it works

- `helpers/environment.js` builds the translation edit screen, installs a fake `fetch` answering the plugin AJAX actions, evaluates the scripts listed in `helpers/scripts.js` and exposes small helpers (typing as a user, toggling filters, running bulk actions, reading the payload).
- `fixtures/` holds the HTML produced by the real PHP renderers (`TranslationMetaBoxRenderer` and `TranslationEntriesListTable`). Regenerate it after changing that markup:

  ```sh
  npm run fixtures
  ```

- When a script is added, renamed or split, update `helpers/scripts.js` together with `EditScreenAssets`: it must list the same files in the same order. A PHPUnit test (`EditScreenAssetsTest`) fails when they drift apart.

## Running these tests in CI (not enabled)

The JavaScript tests are not run by the CI today. They are cheap (about a second once dependencies are installed) and need nothing but Node.js, which GitHub-hosted runners provide. This section records how to enable them without editing the files managed by `evlist/codespaces-grafting` (`.github/workflows/cs-grafting-*.yml`, `.devcontainer/`, see the rules in `.devcontainer/docs/FAQ.md`).

What any option needs:

- `tests/js/package-lock.json` is committed, so `npm ci` gives reproducible installs;
- Node.js 20 or later (22 is what the tests were written with);
- the commands `npm ci` then `npm test`, run from `tests/js`.

### Option A: a separate workflow owned by this repository (recommended)

Add a workflow whose name does not start with `cs-grafting-`, for example `.github/workflows/i18nly-js-tests.local.yml` (the `.local.` suffix is the graft convention for repository-owned files). The managed CI workflow stays untouched and the JavaScript job runs in parallel with it.

```yaml
# SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
# SPDX-License-Identifier: GPL-3.0-or-later

name: JavaScript tests

on:
  push:
  pull_request:

jobs:
  js-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          node-version: 22
          cache: npm
          cache-dependency-path: tests/js/package-lock.json

      - name: Install dependencies
        working-directory: tests/js
        run: npm ci

      - name: Run JavaScript tests
        working-directory: tests/js
        run: npm test
```

Things to know:

- It does not read the `WORKFLOWS_CI_ENABLED` and `WORKFLOWS_CI_BRANCHES` switches of the managed workflow. Add a `branches:` filter under `push:` if it must follow the same branch list.
- The workflow file needs its own SPDX header, as above, to keep `reuse lint` green.
- After the next graft upgrade, check with `graft.sh --dry-run` that the file is left alone.

Optional extra step, to catch fixtures that drifted from the PHP renderers. It needs PHP, and the repository already contains the Composer dependencies the test bootstrap loads:

```yaml
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Check that the fixtures are up to date
        run: |
          php tests/js/bin/generate-fixture.php
          git diff --exit-code -- tests/js/fixtures
```

### Option B: the `lint` hook of the managed workflow

The managed CI workflow can run one custom script. It is enabled by adding `lint` to `WORKFLOWS_CI_TESTS` in `.devcontainer/.cs_env.d/30-i18nly.local.env` (or as a GitHub variable), and it runs `scripts/lint.sh` from the plugin directory, that is `plugin/scripts/lint.sh`:

```bash
#!/usr/bin/env bash
set -e

cd ../tests/js
npm ci
npm test
```

Drawbacks compared with option A:

- the script sits inside `plugin/`, so it is part of the distributable plugin unless `scripts/*` is added to `WORKFLOWS_ZIP_EXCLUDE_PATTERNS`, and it is scanned by the Plugin Check step;
- the step runs after the PHP checks in the same job, so a JavaScript failure is reported late and shares the job with PHP setup;
- `lint` is meant for lint scripts, and the same `plugin/scripts/lint.sh` would have to be shared with any future real lint.

Use it only if a single CI job is a hard requirement.
