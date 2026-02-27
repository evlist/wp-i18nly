<!--
SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later OR MIT
-->

<img src="./assets/icon.svg" alt="cs-grafting logo" title="cs-grafting" width="256" style="float:right;max-width:256px;height:auto" />

# .devcontainer — scion maintainer notes

🌱 Quick metaphor (attention)  
This scion is to a repository what a plant scion is to a stock: graft the scion (this Codespace/devcontainer template) into a repo to give it an instant, reproducible development environment.

What’s in the scion (high level)
- devcontainer.json, Dockerfile — container & Codespaces config
- bin/graft.sh — installer/updater script (graft)
- .vscode/ — editor configuration, snippets and stubs (template-managed)
- docs/ — scion-maintainer docs (upgrade guides, internals)
- assets/ — icons and images used in the scion docs
Development tools (installed in container)
- **Web stack**: Apache, MariaDB, PHP (with Xdebug)
- **WordPress**: WP-CLI for command-line WordPress management
- **Code quality**: Composer, PHP_CodeSniffer (phpcs) with WordPress Coding Standards
- **DevOps**: GitHub CLI (gh), REUSE tool for license compliance
- **Common utilities**: curl, jq, less, unzip

PHP extensions: php-mysql, php-xml, php-mbstring, php-curl, php-zip, php-xdebug
Scion structure (short)
```
.devcontainer/
├── README.md             # this file (short)
├── docs/                 # extended scion docs (upgrade guides, internals)
│   ├── FAQ.md            # frequently asked questions
│   └── MAINTAINER.md     # detailed maintainer guide
├── assets/               # icons, images used in scion docs
├── devcontainer.json
├── Dockerfile
├── .cs_env               # base environment variables
├── .cs_env.d/            # additional env files (Debian .d style)
│   └── graft.local.env   # scion reference (defaults in scion, provenance in stock)
├── bin/
│   └── graft.sh          # run this to graft the scion into a repo
├── sbin/
│   ├── bootstrap.sh      # one-time init: FS, Apache, MariaDB, WP, plugins
│   ├── bootstrap.sh.d/   # modular bootstrap hooks (Debian .d style)
│   │   ├── 10-aliases.sh # shell aliases (graft, upgrade-scion, export-scion)
│   │   ├── 20-plugins.sh # plugin installation and activation
│   │   └── 50-bootstrap.local.sh # local customizations (not overwritten)
│   ├── start.sh          # recurring: start MariaDB, Apache (on every start/attach)
│   ├── start.sh.d/       # modular start hooks (extensible for future needs)
│   └── merge-env.sh      # merges .cs_env and .cs_env.d/*
├── tmp/                  # temporary files (gitignored)
├── var/                  # runtime data (gitignored)
└── wp-content/           # WordPress content customizations
```

.vscode structure (managed files)
```
.vscode/
├── settings.json         # editor settings (managed with 3-way merge)
├── settings.json.orig    # scion (previous) snapshot
├── launch.json           # debug configurations (managed)
├── launch.json.orig      # scion (previous) snapshot
└── intelephense-stubs/   # PHP stubs for IntelliSense
    └── wp-cli.php        # WP-CLI stubs
```

The `.vscode/` files listed above are **managed by graft.sh** and follow the 3-way merge semantics:
- `.orig` files store the previous scion version for comparison
- Local edits are preserved when they differ from `.orig`
- Interactive prompts let you choose: keep local, accept scion, or merge

All other `.vscode/` files you create are yours and won't be touched by upgrades.

🔁 Upgrade & maintainer quick guide
- Interactive (inside a Codespace) — recommended:
  - `graft upgrade` — interactive update
  - `graft export` — export scion to another repo
  - Aliases: `upgrade-scion`, `export-scion`
- From a workstation:
  ```bash
  curl -L -o ~/Downloads/graft.sh \
    https://raw.githubusercontent.com/evlist/codespaces-grafting/stable/.devcontainer/bin/graft.sh
  chmod +x ~/Downloads/graft.sh
  cd /path/to/your-repo
  bash ~/Downloads/graft.sh      # or: bash ~/Downloads/graft.sh --dry-run
  ```
- Dry-run recommended: `bash bin/graft.sh --dry-run`

**Lifecycle: bootstrap vs start**

- **bootstrap** (postCreateCommand): one-time setup during container creation
  - Filesystem: docroot symlinks, Apache confs, WP-CLI directories
  - MariaDB: initialize data directory (temporary startup for setup)
  - WordPress: download core, create config, install if new, set URLs, configure permalinks
  - Plugins: install and activate from `WP_PLUGINS` list
  - Extensible via hooks in `bootstrap.sh.d/` (sourced in current shell)
  - MariaDB is stopped after setup; **start.sh will restart it later**

- **start** (postStartCommand, postAttachCommand): recurring on every start/attach
  - Services only: start MariaDB (with fallback to direct daemon) and Apache
  - Extensible via hooks in `start.sh.d/` for future service-restart customizations
  - No database provisioning or WordPress setup here

⚠️ File replacement behavior during upgrades

**Scion files (.devcontainer/)** — silently replaced:
- Most files in `.devcontainer/` are replaced during upgrades (rsync with `--delete`)
- **Protected files:** `*.local` and `*.local.*` are excluded from sync (your customizations)
- **Protected directories:** `tmp/`, `var/`, and `.cs_env.d/graft.env` are never overwritten

**Managed files (.vscode/)** — interactive merge:
- Files listed above (settings.json, launch.json, stubs) use 3-way merge with `.orig` snapshots
- You get interactive prompts to preserve local changes
- Other `.vscode/` files you create are never touched

Update semantics (.vscode/ managed files)
- New scion file → added and scion (previous) saved as `.orig`.
- Local edits preserved when they differ from `.orig` (interactive choices: keep, replace, backup+replace, save scion sample as `.dist`, or 3‑way merge).
- Scion samples saved as `.dist` when keeping local changes.
- Like Debian's dpkg: `.orig` = previous version, new version compared against local edits.

Troubleshooting tips
- If `git check-ignore` shows required paths are ignored, fix `.gitignore` or run interactively — non‑dry‑run installs abort to avoid hiding scion files.
- Use `.devcontainer/docs/` for step‑by‑step maintainer procedures and to record merging/upgrading policies.

Notes on naming and scope
- Display name: `codespaces-grafting` (short alias: `cs-grafting`). Current implementation and examples target WordPress, but the pattern is generic and reusable for other repositories.

📖 More help
- [FAQ](docs/FAQ.md) — frequently asked questions
- [MAINTAINER.md](docs/MAINTAINER.md) — detailed maintainer procedures

## License

This project is dual-licensed:

- GPL-3.0-or-later OR
- MIT

You may choose either license. See the LICENSE file and LICENSES/ directory for full texts.