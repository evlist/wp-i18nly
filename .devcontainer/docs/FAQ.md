<!--
SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later OR MIT
-->

# FAQ — Frequently Asked Questions

## Installation & Setup

### Q: What's the fastest way to get started?
**A:** Click the Codespaces badge in the README of this repository, create a new Codespace, and use the interactive installer from within the Codespace. It will guide you through exporting the scion into your repository.

### Q: Can I use graft.sh without GitHub Codespaces?
**A:** Yes! You can download and run `graft.sh` from any workstation with Git, Bash, and rsync installed. See the Quick Start section in the main README.

### Q: Do I need to be a maintainer to use graft.sh?
**A:** No. End users can run `graft.sh install` or `graft.sh export` to apply the scion to their repository. Advanced flags like `--push` and `--dry-run` are available for automation and careful review.

### Q: What if my repository already has a `.devcontainer/` or `.vscode/` folder?
**A:** The installer runs a preflight check to detect potential conflicts. If files would be overwritten, it prompts you interactively with options: keep local changes, replace, backup+replace, or save a sample copy. Use `--dry-run` to preview without committing.

---

## Understanding Scion, Stock & Graft

### Q: What does "scion," "stock," and "graft" mean in this context?
**A:** These terms come from gardening:
- **Scion** = the devcontainer template (this repository)
- **Stock** = your repository receiving the scion
- **Graft** = the act of applying the scion to your repository

Think of it like grafting a fruit tree branch onto a rootstock: the scion (template) doesn't replace the stock (your repo), it integrates with it.

### Q: Is my repository modified permanently?
**A:** No. The scion files are added to your repository, but you control them. You can:
- Accept all changes and commit
- Cherry-pick which files to commit
- Reject the graft entirely (the installer is non-destructive in `--dry-run`)

### Q: Can I use the scion with projects other than WordPress?
**A:** Yes! The current examples target WordPress, but the pattern is generic. You can customize `.devcontainer/`, remove WordPress-specific parts, and add your own tools and scripts.

---

## Updates & Maintenance

### Q: How do I update the scion to a newer version?
**A:** Run `bash .devcontainer/bin/graft.sh upgrade` (or `upgrade-scion` inside a Codespace). The script will:
1. Clone the latest scion
2. Compare your local files with the previous scion version
3. Prompt you for each change

### Q: What are `.orig`, `.dist`, and `.bak` files?
**A:** These are part of the Debian-inspired update mechanism:
- **`.orig`** (scion previous) = snapshot of the file when the scion was last applied. Used to detect your local changes.
- **`.dist`** (scion sample) = the latest scion version, saved when you choose to keep your local version instead of updating.
- **`.bak.<timestamp>`** = timestamped backup created before a destructive replace action.

### Q: Should I commit `.orig` and `.dist` files?
**A:** `.orig` should be committed (it tracks the scion baseline). `.dist` files are typically gitignored but can be committed if you want to preserve samples. Check your `.gitignore`.

### Q: What if I have local edits to a scion file?
**A:** When updating, you'll be prompted with options:
- **Keep local** = ignore the new scion version
- **Replace** = accept the new version (loses your edits)
- **Backup + Replace** = save your version as `.bak.<timestamp>` then update
- **Save sample** = keep local but save the new version as `.dist`
- **Merge** = attempt a 3-way merge using the scion baseline

---

## Container Lifecycle

### Q: Why are there two scripts—bootstrap.sh and start.sh? When does each run?
**A:** They handle two different phases of the Codespace lifecycle:

**bootstrap.sh** (runs once during container creation):
- One-time setup: Creates the WordPress filesystem, Apache configuration, initializes MariaDB data directory
- Starts MariaDB temporarily, creates the database and user (idempotent)
- Downloads WordPress core, creates wp-config.php, installs WordPress if new
- Sets URLs, configures permalinks
- Installs and activates plugins
- **Then stops MariaDB** at the end

**start.sh** (runs every time the container starts or restarts):
- Starts MariaDB (with fallback to direct daemon if service startup fails)
- Starts Apache (with fallback to apache2ctl then direct apache2 -k start)
- That's it. No provisioning, no setup—just service startup

Why split them?
- **Idempotence**: If you restart the Codespace, you don't re-download WordPress, re-create the database, or re-activate plugins
- **Speed**: Subsequent starts are fast (just start services, not full bootstrap)
- **Clarity**: Clear separation between one-time init and recurring startup
- **Robustness**: Service startup fallbacks work reliably because they're separate from complex provisioning logic

### Q: Why does bootstrap.sh stop MariaDB at the end?
**A:** To ensure clean separation: `bootstrap.sh` sets everything up, then hands control to `start.sh` for service management. When the container restarts (e.g., Codespace restart), `start.sh` is what runs, and it's designed to start fresh services.

If bootstrap left MariaDB running, then:
- On first creation it would be running
- On restart (where only start.sh runs) it would need different logic
- The scripts would be harder to reason about

By stopping MariaDB at the end of bootstrap, both scripts have clear, predictable entry points.

### Q: I restarted the Codespace but WordPress is not responding. What do I do?
**A:** Run `start.sh` manually to restart services:
```bash
bash .devcontainer/sbin/start.sh
```

This starts MariaDB and Apache. Your WordPress data is already present from `bootstrap.sh` (which only runs once). If issues persist:
1. Check MariaDB: `sudo systemctl status mariadb`
2. Check Apache: `sudo systemctl status apache2`
3. Check logs: `sudo tail -n 50 /var/log/mysql/error.log` or `/var/log/apache2/error.log`

### Q: Can I add custom startup tasks in start.sh.d/?
**A:** Yes! Create hooks in `.devcontainer/sbin/start.sh.d/` for tasks that should run on every start/attach:

```bash
#!/usr/bin/env bash
# .devcontainer/sbin/start.sh.d/10-healthcheck.local.sh
log "Checking WordPress health..."
curl -I http://localhost/wp-login.php || log "Warning: WordPress not responding"
```

These are useful for monitoring, cache warming, log rotation, or other recurring maintenance—things that should happen every time services start but don't require full re-initialization.

---

## Troubleshooting

### Q: The installer aborts saying files would be ignored by .gitignore. What do I do?
**A:** Your repository's `.gitignore` prevents the scion from being installed. Either:
1. Update `.gitignore` to exclude the scion paths (`.devcontainer/`, `.vscode/`)
2. Run the installer interactively to review and fix ignores
3. Use `--dry-run` to see exactly which files are affected

### Q: I ran the graft but forgot `--dry-run`. Can I undo it?
**A:** Yes. The installer creates a Git branch (default: `graft/TIMESTAMP`). If you haven't pushed or committed, simply:
```bash
git checkout main      # switch back to main
git branch -D graft/TIMESTAMP  # delete the graft branch
```
If you've already committed, use `git revert` or `git reset` to undo.

### Q: Files are present locally but not committed. Why?
**A:** Your `.gitignore` likely ignores them. Check with:
```bash
git check-ignore -v .devcontainer/
git check-ignore -v .vscode/
```
Then either update `.gitignore` or manually add/commit the files.

### Q: The merge attempt failed. Now what?
**A:** A merge can produce conflicts if your local edits and the scion changes are incompatible. The script will:
1. Show a preview of the merged result
2. Let you accept or discard it
3. Fall back to `--keep local` if you discard

If unsure, use `--dry-run` to preview before running the real update.

### Q: I want to downgrade or use a specific scion version. How?
**A:** Edit `.devcontainer/.cs_env.d/graft.local.env` and change the `SCION` field. For example:
```bash
# Graft scion provenance - generated by graft.sh export
# This file tracks which scion version was grafted into this stock repository
SCION=evlist/codespaces-grafting@v1.0.0
SCION_COMMIT=abc123def456...
SCION_INSTALLED_AT=2026-01-05T10:30:45Z
```
Then run `bash .devcontainer/bin/graft.sh upgrade` to re-apply that version.

You can also use the `--scion` flag:
```bash
# Use a different ref from the default scion (shortcut)
graft upgrade --scion @v1.0.0

# Or specify a completely different scion
graft upgrade --scion other-user/other-scion@stable
```

---

## Design Decisions

### Q: Wait, copying files from one repo to another? Isn't that a terrible pattern?
**A:** For software libraries, absolutely! That's why we have package managers, dependency management tools, and proper versioning. But for **system configuration and infrastructure setup**, file copying is actually the standard approach:

- **Operating systems**: Installation images copy thousands of files to disk
- **Package managers**: `apt`, `yum`, `brew` all copy files from repositories to your system
- **Container images**: Docker's `COPY` and `ADD` instructions literally copy files into images
- **Configuration management**: Ansible, Chef, Puppet all copy configuration files to target systems

**Why is this appropriate for Codespaces?**

Codespaces are **virtual machines** (or containers with VM-like isolation). Just like installing an OS or configuring a server, you need to copy configuration files, scripts, and development environment setup to the machine. This isn't a code dependency—it's infrastructure provisioning.

**What we're doing differently:**

- **Debian-inspired semantics**: We borrowed proven patterns from `dpkg` (`.orig` snapshots, 3-way merges, `.dist` samples)
- **Selective sync**: The `*.local.*` exclusion pattern lets you keep customizations
- **Update-safe**: Upgrades preserve your local changes through interactive conflict resolution
- **Transparent**: `--dry-run` shows exactly what will change before applying

Think of it as "apt install devcontainer-template" rather than "npm install library".

### Q: Shouldn't we use git subtree or submodules instead?
**A:** These are valid approaches worth considering! However, they come with trade-offs:

**git subtree/submodules challenges:**
- **Complexity**: Subtree merge strategies and submodule update workflows confuse many users
- **Merge conflicts**: Resolving conflicts in subtrees/submodules can be tricky, especially with binary files or large changesets
- **History pollution**: Subtree merges can clutter your repository history
- **Tooling gaps**: Not all Git GUIs handle submodules well; CI/CD pipelines need special configuration

**Current approach benefits:**
- **Simple mental model**: "Copy these files, exclude these patterns" is intuitive
- **File-level control**: You see exactly which files exist in your repo (no hidden .git/modules)
- **Standard Git workflow**: No special commands needed after initial graft
- **CI-friendly**: Works everywhere Git works, no submodule init/update steps

**That said**, we're open to exploring alternatives! If you have experience with subtree/submodules in devcontainer contexts and want to propose improvements:
- Open a [GitHub Discussion](https://github.com/evlist/codespaces-grafting/discussions) to share your approach
- File an [issue](https://github.com/evlist/codespaces-grafting/issues) with a concrete proposal

Contributions and alternative implementations are welcome!

### Q: Why save a `.orig` baseline instead of just replacing files?
**A:** It allows future updates to detect whether you've made local changes. When updating, the script compares:
- Local file vs. scion (previous) → detects your edits
- Scion (previous) vs. scion (new) → detects what changed upstream

This is inspired by Debian's dpkg, which uses a similar approach for package config files.

### Q: Why not auto-source `./.cs_env` at runtime?
**A:** The script is designed to work in minimal environments (fresh clones, CI) without hidden dependencies. Auto-sourcing a workspace file can introduce surprising behavior. Instead, you can:
- Export env variables before running the script
- Create a wrapper script that sources and then calls graft.sh
- Set environment variables in CI/Codespaces Secrets

### Q: Why are there separate `.cs_env` and `.cs_env.d/` directories?
**A:** The `.d/` convention (from Debian) allows modular configuration:
- `.cs_env` = baseline defaults
- `.cs_env.d/*` = optional modules, sourced in alphabetical order
- Each module can be independently enabled/disabled

This scales better than a monolithic `.env` file.

### Q: What's the difference between `install`, `export`, and `upgrade`?
**A:**
- **`install`** (scion → stock) = apply the scion into a repository
- **`export`** (stock as scion) = package your repository as a new scion template
- **`upgrade`** (stock with scion) = update the scion in an already-grafted repository

### Q: Is this specific to testing WordPress plugins?
**A:** No! Testing WordPress plugins happens to be my own use case, but the pattern is easily adaptable:

- **WordPress themes**: Create a `25-themes.local.sh` hook (see [Customization](#8-customization))
- **WordPress core development**: Modify bootstrap.sh to clone WP from git instead of downloading releases
- **WP-CLI plugin development**: Focus on CLI rather than web interface

The scion provides a ready-to-use WordPress environment, but you control what you test in it. If you have a specific use case or need guidance, open a [GitHub Discussion](https://github.com/evlist/codespaces-grafting/discussions) or [issue](https://github.com/evlist/codespaces-grafting/issues).

### Q: Is this specific to WordPress, LAMP, or PHP?
**A:** The **pattern** (scion/stock grafting with Debian-style `.d` directories) is completely generic and reusable for any tech stack.

The **current implementation** happens to install a LAMP stack (Linux, Apache, MariaDB, PHP) and WordPress because that's what I needed. But:

- `Dockerfile` can install Node.js, Python, Ruby, Go, or any runtime you need
- `bootstrap.sh` can set up databases (PostgreSQL, MongoDB), message queues (Redis, RabbitMQ), or any services
- The `.d` directory pattern works for any configuration management

**Want to adapt it?**
1. Fork this repository
2. Replace the LAMP/WordPress parts with your stack
3. Keep the grafting pattern, `.local.*` exclusions, and update semantics
4. Share your adaptation! Open a [Discussion](https://github.com/evlist/codespaces-grafting/discussions) to showcase alternative implementations

### Q: Why a single container? Docker is about isolation—shouldn't we use separate containers for the web server, database, and terminal?
**A:** This is a **development and testing environment**, not a production deployment. Single-container simplicity wins here:

**Advantages of single container:**
- **Direct filesystem access**: Edit files, check logs, inspect databases without `docker exec` or volume mounts
- **Simple debugging**: All processes visible with `ps`, `top`, no network/container boundary to cross
- **Fast startup**: One container to build and start, not three with orchestration
- **Lower cognitive load**: Focus on your code, not on Docker networking and volume management

**Multi-container complexity:**
- Database connection strings become trickier (container hostnames vs. localhost)
- Log aggregation needs setup (each container logs separately)
- Debugging requires `docker-compose exec db mariadb` instead of just `mariadb`
- File permissions issues multiply across mounted volumes

**Historical note:** This project actually started with a 3-container setup (web, db, CLI). It was painful. Check the [early commits](https://github.com/evlist/codespaces-grafting/commits/main) if you're curious about what we escaped from! 😅

For **production deployments**, absolutely use container isolation. For **local development**, simplicity > purity.

---

## Advanced Usage

### Q: Can I customize the scion before installing it?
**A:** Yes. You can:
1. Fork this repository
2. Make your changes
3. Run `graft.sh --scion <your-fork>` to use your custom version
4. Or set `SCION` to your fork in `.devcontainer/.cs_env.d/graft.local.env`

### Q: What does the `--scion @ref` syntax do?
**A:** It's a shortcut to use your default scion repository with a different branch/tag/commit.

Instead of writing:
```bash
graft export --stock your-repo --scion evlist/codespaces-grafting@main
```

You can write:
```bash
graft export --stock your-repo --scion @main
```

The `@ref` syntax reads the base repository from `.devcontainer/.cs_env.d/graft.local.env` and replaces only the reference. Useful for testing different branches without typing the full repo path.

Examples:
- `--scion @main` → use main branch
- `--scion @v1.0.0` → use specific tag
- `--scion @abc123` → use specific commit

### Q: How do I use graft.sh in CI?
**A:** Example GitHub Actions workflow:
```yaml
- name: Preview graft changes
  run: bash .devcontainer/bin/graft.sh --dry-run

- name: Apply graft
  run: bash .devcontainer/bin/graft.sh --non-interactive --push
```

### Q: Can I graft into a non-GitHub repository?
**A:** Yes, as long as it's a Git repository with an origin remote. `graft.sh` uses `git` commands, not GitHub-specific APIs (except for pushing with `gh`).

### Q: What about monorepos? Can I graft multiple projects?
**A:** `graft.sh` works at the repository root. For monorepos, you'd need to:
1. Run it once per project directory (if they have separate `.devcontainer/`)
2. Or create a unified scion that supports multiple subprojects

---

## Support & Contributions

### Q: I found a bug or have a feature request. Where do I report it?
**A:** Open an issue on the [GitHub repository](https://github.com/evlist/codespaces-grafting/issues). Please include:
- What you're trying to do
- The command you ran
- Output/error messages
- Relevant context (OS, Git version, Bash version)

### Q: Can I contribute improvements?
**A:** Absolutely! Fork the repository, make your changes, and submit a pull request. Make sure to:
- Test with `--dry-run` on a sample repository
- Update documentation if you change behavior
- Follow the existing code style

### Q: What's the license?
**A:** Dual-licensed: GPL-3.0-or-later OR MIT. You can choose whichever suits your needs.

---

## Performance & Limitations

### Q: How long does a typical graft take?
**A:** Usually 10–30 seconds, depending on:
- Network speed (cloning the scion from GitHub)
- Disk speed (copying/syncing files)
- Number of files to process
- Whether prompts are shown (interactive mode is slower)

Use `--non-interactive` to skip prompts and speed up automated runs.

### Q: Are there any size limits?
**A:** No hard limits, but:
- Cloning a very large repository may take time
- Many files to sync will be slower
- Very large `.vscode/` configs may conflict more

### Q: Can I use graft.sh on Windows?
**A:** Yes, if you have Git Bash, WSL, or a similar Bash environment. The script is pure Bash with standard Unix tools (rsync, git, etc.).

### Q: PHPCS works in terminal but no diagnostics appear in VS Code Problems. Why?
**A:** This is usually an extension host issue, not a PHPCS issue.

If `phpcs` works in terminal but VS Code Problems stays empty:

1. Open **Output** and check the extension channel used for PHPCS (for this project: PHPSAB).
2. Check **Log (Extension Host)** for runtime errors.
3. If you see `PendingMigrationError: navigator is now a global in nodejs`, a third-party extension is crashing the host before PHPCS diagnostics are published.

Known conflicting extension in this environment:
- `GitHub.copilot-chat` (certain versions)

Recommended action:
- Disable/uninstall the crashing extension locally and reload the window.
- Keep `valeryanm.vscode-phpsab` enabled for PHPCS diagnostics.

Note: `ikappas.phpcs` is deprecated and should not be used in this repository.

---

## 8. Customization

### Q: How do I customize the bootstrap process?
**A:** The scion uses Debian-style `.d` directories for modular configuration:

**Environment variables** (`.devcontainer/.cs_env.d/`):
- Base configuration in `.cs_env` is managed by the scion
- Create `.cs_env.d/*.local.env` files to override defaults (e.g., `custom.local.env`, `theme.local.env`)
- Files sourced in alphabetical order after base `.cs_env`
- The `.local` suffix excludes them from sync during upgrades

**Bootstrap hooks** (`.devcontainer/sbin/bootstrap.sh.d/`):
- Scion-provided hooks: `10-aliases.sh`, `20-plugins.sh` (managed, will be overwritten during upgrades)
- Your custom hooks: Create `*.local.sh` files (e.g., `25-themes.local.sh`, `40-custom.local.sh`)
- All hooks sourced in alphabetical order during container startup
- Custom `.local.sh` hooks are excluded from sync during upgrades
- Inherit all variables and functions from `bootstrap.sh`

**Naming convention:**
- `10-*.sh`: Shell environment (scion-managed: 10-aliases.sh)
- `20-*.sh`: WordPress extensions (scion-managed: 20-plugins.sh)
- `25-*.local.sh`, `30-*.local.sh`: Your custom extensions (themes, etc.)
- `40-*.local.sh`, `50-*.local.sh`: Your custom commands and tweaks

### Q: How do I test a theme instead of a plugin?
**A:** Create `.devcontainer/sbin/bootstrap.sh.d/25-themes.local.sh` (note the `.local.sh` suffix to prevent it from being overwritten during upgrades):

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

Then add to `.devcontainer/.cs_env.d/theme.local.env`:
```bash
THEME_SLUG="my-theme"
THEME_DIR="themes/my-theme"
```

The `.local.sh` suffix ensures your customization survives upgrades. The hook will run after plugin installation (20-plugins.sh) due to alphabetical ordering.

### Q: Can I skip plugin installation in my fork?
**A:** Don't modify `20-plugins.sh` (it's part of the scion and will be overwritten during upgrades). Instead, simply don't define plugin variables:

- By default, `PLUGIN_SLUG`, `PLUGIN_DIR`, and `WP_PLUGINS` are empty in `.devcontainer/.cs_env`
- Plugin configuration in `.devcontainer/.cs_env.d/50-plugins.local` is NOT copied during grafts (the `.local` suffix excludes it)
- If you've created custom plugin configuration files, simply delete them or leave the variables empty

The `20-plugins.sh` hook will run but do nothing when these variables are undefined or empty.

### Q: How do I add custom WP-CLI commands during bootstrap?
**A:** Create `.devcontainer/sbin/bootstrap.sh.d/40-custom.local.sh` (note the `.local.sh` suffix to prevent it from being overwritten during upgrades):

```bash
#!/usr/bin/env bash
log "Running custom WP-CLI commands..."

# Import test content
wp plugin install wordpress-importer --activate
wp import /path/to/content.xml --authors=create

# Configure settings
wp option update posts_per_page 20
wp rewrite structure '/%year%/%monthnum%/%postname%/'
```

You can also create multiple hooks like `40-import-content.local.sh`, `45-configure-options.local.sh`, etc. All `.local.sh` files are excluded from scion updates.

---

Last updated: 2026-01-05
