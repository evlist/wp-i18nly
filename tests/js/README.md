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
