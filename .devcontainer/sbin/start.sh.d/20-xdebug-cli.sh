#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

# Ensure Xdebug does not auto-start for every PHP CLI command.
# Keep debug available when explicitly requested via XDEBUG_TRIGGER.

if ! command -v log >/dev/null 2>&1; then
  log() { printf "[start:xdebug] %s\n" "$*"; }
fi

if ! command -v php >/dev/null 2>&1; then
  log "php command not found; skipping Xdebug CLI tuning"
  return 0 2>/dev/null || exit 0
fi

updated=0
for conf_dir in /etc/php/*/cli/conf.d; do
  [ -d "$conf_dir" ] || continue
  [ -f "$conf_dir/20-xdebug.ini" ] || continue

  override_file="$conf_dir/99-codespaces-grafting-xdebug-cli.ini"

  sudo tee "$override_file" >/dev/null <<'EOF'
; Managed by codespaces-grafting start hook.
; Prevent noisy CLI debug attempts while keeping opt-in debugging possible.
xdebug.start_with_request=trigger
xdebug.log=/dev/null
xdebug.log_level=0
EOF

  updated=$((updated + 1))
done

if [ "$updated" -eq 0 ]; then
  log "No Xdebug CLI config found; nothing to update"
else
  log "Applied Xdebug CLI override in $updated PHP CLI configuration(s)"
  effective="$(php -i | sed -n 's/^xdebug.start_with_request => \(.*\) => .*/\1/p' | head -n 1 || true)"
  [ -n "$effective" ] && log "Effective xdebug.start_with_request: $effective"
fi
