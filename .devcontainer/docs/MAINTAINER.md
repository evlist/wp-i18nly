<!--
SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later OR MIT
-->

# MAINTAINER — scion operational notes

This document provides detailed maintainer guidance for people who maintain or evolve the scion (the `.devcontainer/` template) and for repository maintainers who will graft the scion into their projects.

Keep this file with the scion so maintainer guidance travels with the template and doesn't pollute the stock repository.

Summary
- Purpose: explain preflight checks, environment file conventions, updater semantics (.orig/.dist/.bak), dry‑run behavior and available command-line flags.
- Audience: scion maintainers and repository maintainers who accept updates.
- Assumption: the graft.sh script can be invoked with flags (--scion, --stock, --dry-run, etc.) or without flags to use defaults.

Contents
- Preflight & .gitignore checks
- Environment files (.devcontainer/.cs_env and ./ .cs_env)
- Updater semantics (.orig, .dist, .bak.*) and interactive choices
- Dry‑run and automation (flags and CI usage)
- Common commands and aliases
- Troubleshooting
- Renaming / migration notes

---

## Preflight & .gitignore checks

Before writing files into a stock repository the installer runs a preflight to detect whether any required template paths would be ignored by the repo's `.gitignore`. This is important because writing files that are then ignored can silently hide essential template content.

Recommended preflight sequence (illustrative)
1. Generate the list of destination paths you intend to create (relative to repo root).
2. Pipe that list to `git check-ignore -v --stdin` to discover any ignores:

```bash
# example: produce a newline-separated list of files/directories to write,
# then check which would be ignored by the target repo.
printf "%s\n" ".devcontainer/" ".vscode/" ".devcontainer/bin/graft.sh" | git check-ignore -v --stdin
```

Behavior
- Non‑dry‑run installs should abort if any required paths are ignored. This prevents accidentally leaving repositories without needed scion files.
- Dry‑run should report ignored paths and continue without changing files so maintainers can fix `.gitignore` before committing.

Action for maintainers
- When adding new required files to the scion, update the installer so the preflight list includes the new paths.
- Document any special-case files that may be intentionally ignored and provide guidance on how to materialize them (e.g., copy from `.devcontainer/.cs_env` to `./.cs_env`).

---

## Environment files (.devcontainer/.cs_env and ./ .cs_env)

Rationale
- Many projects use `.env` already; to avoid overwriting or conflicting, scion-specific configuration lives in `.devcontainer/.cs_env` (the sample) and can be copied by repo owners to `./.cs_env` in their repo root when they want to override defaults.

Important conventions
- The scion ships a sample `.devcontainer/.cs_env`. This is not automatically sourced by the installer at runtime.
- The installer reads environment variables (e.g., UPSTREAM_SCION) from the environment. In Codespaces/CI you can export these variables for the session.
- You may include a sample `.devcontainer/.cs_env` with defaults for maintainers to copy, e.g.:
  ```bash
  UPSTREAM_SCION="evlist/codespaces-grafting@stable"
  # Optional hard override:
  # GRAFT_URL="https://raw.githubusercontent.com/evlist/codespaces-grafting/stable/.devcontainer/bin/graft.sh"
  ```

Operational practice
- In Codespaces the environment is commonly configured via repository-level Secrets / Environment — maintainers can set UPSTREAM_SCION there.
- For one-off installs from a workstation, the README shows a copy/paste URL (convenient). For automation, prefer setting UPSTREAM_SCION or GRAFT_URL in CI.

Why we don't auto-source `./.cs_env`
- The script should be usable in minimal environments (CI, fresh clones). Auto-sourcing a workspace file can introduce surprising behavior; instead, we honor already-exported environment variables. Repo maintainers who want to use `./.cs_env` simply export values or run a small wrapper that exports them and calls the installer.

---

## Updater semantics and artifact conventions

The installer/updater preserves local edits and uses conservative semantics inspired by Debian's dpkg config file handling.

Conventions (Debian-like)
- `.orig` — scion (previous) snapshot: the file as it was when the scion was last applied. Used as the merge ancestor (like dpkg's .dpkg-old).
- `.dist` — scion sample: the new scion version saved when repository maintainers elect to keep their local version (like dpkg's .dpkg-dist).
- `.bak.<timestamp>` — timestamped backups created before destructive replacements.

High-level rules (like Debian dpkg)
- New scion file (not present locally):
  - Add the file, create a `.orig` (scion previous) snapshot next to it.
- Scion changed, local unmodified (matches `.orig`):
  - Replace the local file with scion (new) and update `.orig`.
- Local differs from `.orig` (scion previous):
  - Present interactive choices:
    - Keep local (no change).
    - Replace local (overwrite and create `.bak.<ts>`).
    - Backup + replace (save `.bak.<ts>` then replace).
    - Save scion sample as `<filename>.dist` (keep local, but save scion new in a `.dist` copy).
    - Attempt a 3‑way merge using the `.orig` (scion previous) as merge base.
- Always preserve `.orig` to allow future 3-way merges and to track the previous scion version.

Interactive guidance
- In interactive Codespace runs the script should show a short diff and the options above.
- For non-interactive or CI driven updates, provide flags (e.g., `--yes` to accept a sensible default behavior) and require `--dry-run` in CI to verify what would change before applying.

---

## Dry‑run and automation

Available flags
- `--dry-run` — report intended actions and exit without modifying files. Use to preview changes before applying.
- `--non-interactive` — accept defaults and do not prompt for user input. Useful in CI/automation.
- `--push` — push the graft branch to origin after applying changes.
- `--debug` — enable debug output to understand script behavior.
- `--scion <spec>` — specify the scion repository (owner/repo, URL, or local path).
- `--stock <spec>` — specify the stock repository (owner/repo, URL, or local path).
- `--tmp <dir>` — use a custom temporary directory for cloning.
- `--target-stock-branch <name>` — custom branch name for the graft branch (default: graft/TIMESTAMP).

CI usage
- Recommended pattern:
  1. Run `bash bin/graft.sh --dry-run` as a pre-flight check to verify what would change.
  2. Inspect results before applying in controlled automation (review changes or create a pull request).
  3. Run `bash bin/graft.sh --push` to apply and push in automated workflows.

Exit codes
- 0 = success
- non-zero = failure (script aborts on errors due to `set -euo pipefail`)

---

## Common commands and aliases

Aliases provided in the Codespace image (convenience for maintainers)
- `graft` — alias to `.devcontainer/bin/graft.sh` (all commands)
- `upgrade-scion` — alias to `graft upgrade` (interactive update)
- `export-scion` — alias to `graft export` (export to another repo)

These aliases are defined in `.devcontainer/sbin/bootstrap.sh.d/10-aliases.sh`.

Common commands
- Initial install (workstation):
  ```bash
  curl -L -o ~/Downloads/graft.sh https://raw.githubusercontent.com/evlist/codespaces-grafting/main/.devcontainer/bin/graft.sh
  chmod +x ~/Downloads/graft.sh
  cd /path/to/your-repo
  bash ~/Downloads/graft.sh
  ```
- Upgrade interactively (inside Codespace):
  ```bash
  upgrade-scion
  ```
- Dry-run (preview):
  ```bash
  bash bin/graft.sh --dry-run
  ```

---

## Container Lifecycle: bootstrap and start

The Codespace/devcontainer lifecycle is split into two phases to separate one-time initialization from recurring service startup. This design ensures idempotent behavior and clean separation of concerns.

### bootstrap.sh (postCreateCommand — one-time)

Runs **once** during container creation. Handles all setup that should happen only at initialization:

- **Filesystem preparation**: Creates WordPress docroot symlinks, sets Apache configurations (`servername`, `codespaces-https`, `dev-index`), and initializes WP-CLI directories.
- **MariaDB initialization**: Initializes the MariaDB data directory if it doesn't exist; starts MariaDB temporarily for setup, creates the database and user (idempotent), then **stops MariaDB**.
- **WordPress setup**: Downloads WordPress core (if missing), creates `wp-config.php`, installs WordPress (if not already installed), sets home/siteurl options, and configures permalinks.
- **Plugins**: Installs and activates plugins from the `WP_PLUGINS` list and links the workspace plugin.
- **Extensibility**: Runs hooks from `bootstrap.sh.d/` in alphabetical order (sourced in the current shell, inherit all variables and functions).

**Important**: `bootstrap.sh` stops MariaDB at the end. The `start.sh` script will restart it on the next container start or attach.

### start.sh (postStartCommand, postAttachCommand — recurring)

Runs **every time** the container starts or restarts (including Codespace restarts and re-attachments). Keeps service startup minimal and idempotent:

- **MariaDB startup**: Starts MariaDB via service (with fallback to direct `mariadbd` daemon if service startup fails).
- **Apache startup**: Starts Apache via service (with fallback to `apache2ctl` then `apache2 -k start`).
- **Extensibility**: Runs hooks from `start.sh.d/` in alphabetical order (sourced in the current shell for future customizations).

**Note**: No database provisioning or WordPress setup; those happen once in `bootstrap.sh`.

### Lifecycle summary

```
Container creation (Codespace starts):
  1. postCreateCommand → bootstrap.sh
     • One-time FS, MariaDB init, WordPress install, plugins
     • Stops MariaDB after setup
  2. postStartCommand → start.sh
     • Starts MariaDB & Apache
     • Ready for development

Codespace restart or re-attach:
  1. postStartCommand → start.sh
     • Starts MariaDB & Apache
     • WordPress config and data already present (created by bootstrap)
     • Ready to develop

```

---

## Bootstrap customization (bootstrap.sh.d/)

The scion uses a modular bootstrap system inspired by Debian's `.d` directories. During container startup, `bootstrap.sh` sources all scripts in `bootstrap.sh.d/` in alphabetical order.

### Creating custom bootstrap hooks

1. **Create a numbered script** in `.devcontainer/sbin/bootstrap.sh.d/`:
   - `10-*.sh`: Shell environment setup (aliases, functions) — provided by scion
   - `20-*.sh`: WordPress extensions (plugins, themes) — provided by scion
   - `30-*.sh`: Content import and configuration
   - `50-*.sh`: Final tweaks and customization
   - **Use `.local.sh` suffix** for your customizations to prevent them from being overwritten during upgrades

2. **Make it executable**:
   ```bash
   chmod +x .devcontainer/sbin/bootstrap.sh.d/25-themes.local.sh
   ```

3. **Scripts inherit context** from `bootstrap.sh`:
   - All environment variables (`$PLUGIN_SLUG`, `$WP_PLUGINS`, `$DOCROOT`, etc.)
   - Helper functions (`log()`, `die()`, `wp` alias)
   - Full WordPress environment

### Example: Theme development hook

Create `.devcontainer/sbin/bootstrap.sh.d/25-themes.local.sh` (note the `.local.sh` suffix):

```bash
#!/usr/bin/env bash
log "Linking workspace theme..."
THEME_SLUG="${THEME_SLUG:-my-theme}"
THEME_DIR="${THEME_DIR:-themes/my-theme}"

sudo mkdir -p "$DOCROOT/wp-content/themes"
if [ -d "$WORKSPACE/$THEME_DIR" ]; then
    sudo ln -sfn "$WORKSPACE/$THEME_DIR" "$DOCROOT/wp-content/themes/$THEME_SLUG"
    wp theme activate "$THEME_SLUG"
fi
```

Add configuration to `.devcontainer/.cs_env.d/theme.local.env`:
```bash
THEME_SLUG="my-theme"
THEME_DIR="themes/my-theme"
```

### Existing hooks (provided by scion)

- **10-aliases.sh**: Defines shell aliases (`graft`, `upgrade-scion`, `export-scion`) — **managed by scion** (runs during bootstrap)
- **20-plugins.sh**: Links workspace plugin, installs additional plugins from `WP_PLUGINS` — **managed by scion** (runs during bootstrap)

**Important**: Don't modify or delete scion-provided hooks (files without `.local` in their name). They will be overwritten during upgrades.

### Start hooks (start.sh.d/)

For future needs, you can create custom hooks in `.devcontainer/sbin/start.sh.d/` to run on every service startup (e.g., health checks, log rotation, custom monitoring). These are sourced after `start.sh` starts MariaDB and Apache.

Example:

```bash
#!/usr/bin/env bash
# .devcontainer/sbin/start.sh.d/10-healthcheck.local.sh
log "Performing health check..."
curl -I http://localhost/wp-login.php || log "Warning: WordPress not responding"
```

### Disabling scion hooks

To disable plugin installation (20-plugins.sh), don't modify the script. Instead:
- Leave `PLUGIN_SLUG`, `PLUGIN_DIR`, and `WP_PLUGINS` empty in your environment files
- The hook will run but do nothing when these variables are undefined

For your own `.local.sh` hooks, you can disable them by:
```bash
mv bootstrap.sh.d/40-custom.local.sh bootstrap.sh.d/40-custom.local.sh.disabled
```

---

## Troubleshooting

Common issues and remedies

1. **Aliases not available in terminal**
   - Symptom: `graft` command not found, even after fresh Codespace.
   - Cause: Aliases are added to `~/.bash_aliases` by `10-aliases.sh` (idempotent, runs once in bootstrap).
   - Fix: Source `~/.bash_aliases` manually: `source ~/.bash_aliases` or create a new terminal tab.

2. **Services not running after Codespace restart**
   - Symptom: MariaDB or Apache doesn't respond after Codespace restart.
   - Cause: `start.sh` runs on postStart/postAttach but may be skipped in edge cases.
   - Fix: Manually run `bash .devcontainer/sbin/start.sh` to restart services.

3. **WordPress database errors on first boot**
   - Symptom: "Error establishing a database connection."
   - Cause: MariaDB initialization may take time on slow containers; `bootstrap.sh` waits up to ~30 seconds.
   - Fix: Check MariaDB logs: `sudo tail -n 50 /var/log/mysql/error.log`; restart `start.sh` if needed.

4. **Files are missing after install (likely ignored)**
4. **Files are missing after install (likely ignored)**
   - Symptom: scion files not present after install (or are present locally but not committed).
   - Check:
     ```bash
     printf "%s\n" ".devcontainer/" ".devcontainer/bin/graft.sh" | git check-ignore -v --stdin
     ```
   - Fix: update `.gitignore` or adjust installer preflight; run installer interactively after adjustments.

5. **Markdown preview blank in browser (Codespace web UI quirk)**
   - Workaround: open in a Chromium-based browser or view README on the GitHub repo page.

6. **Codespace returns 401 or missing envs**
   - Ensure required environment variables are set in the Codespace (Secrets / Repository settings) or copy `.devcontainer/.cs_env` to `./.cs_env` and export the necessary vars locally.

7. **Upstream rename/migration confusion**
   - If the scion repo is renamed, set `UPSTREAM_SCION` to the new `owner/repo@ref` in Codespaces/CI or update the template sample `.devcontainer/.cs_env`.
   - The installer derives `GRAFT_URL` from `UPSTREAM_SCION`; no code change needed if env is updated.

---

## Renaming & migration notes

If you rename the scion repository (for example to `evlist/codespaces-grafting`) follow these steps:
1. Update the template sample: `.devcontainer/.cs_env` to point to the new `owner/repo@ref`.
2. Update README examples (the explicit raw URL examples) to use the new `raw.githubusercontent.com` location for convenience.
3. Encourage repository maintainers to set `UPSTREAM_SCION` in the Codespace or CI environment to the new `owner/repo@ref` so no immediate code changes are required.
4. Optionally, update the baked-in default `UPSTREAM_SCION` in `bin/graft.sh` to the new value.

Note: GitHub creates redirects for renamed repositories, so old raw URLs may continue to work for a while. However, for long-term clarity update explicit URLs in documentation.

---

## Appendix: sample .devcontainer/.cs_env (recommended)

Add this sample to the scion to help maintainers override defaults easily:

```bash
# .devcontainer/.cs_env (sample)
# Format: owner/repo@ref
UPSTREAM_SCION="evlist/codespaces-grafting@stable"
# Optional explicit override:
# GRAFT_URL="https://raw.githubusercontent.com/evlist/codespaces-grafting/stable/.devcontainer/bin/graft.sh"
```

---

If you want, I can:
- Produce a ready-to-commit PR that adds this `MAINTAINER.md` into `.devcontainer/docs/`.
- Or produce a smaller `MAINTAINER.md` if you prefer a shorter checklist-only document.

Which would you like next?