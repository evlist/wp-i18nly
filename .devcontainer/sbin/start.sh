#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

set -euo pipefail

log() { printf "[start] %s\n" "$*"; }

die() { printf "[start:ERROR] %s\n" "$*" >&2; exit 1; }

# Source bash aliases to get DOCROOT and other environment variables
[ -f ~/.bash_aliases ] && source ~/.bash_aliases

WORKSPACE="/workspaces/${GITHUB_REPOSITORY##*/}"

# --- Start: services only ---
log "Preparing MariaDB runtime..."
sudo install -o mysql -g mysql -m 0755 -d /run/mysqld

log "Starting MariaDB via service..."
if ! sudo service mariadb start; then
  log "Service start failed, trying to launch mariadbd directly..."
  sudo /usr/sbin/mariadbd \
    --datadir=/var/lib/mysql \
    --socket=/run/mysqld/mysqld.sock \
    --bind-address=127.0.0.1 \
    --skip-networking=0 \
    >/var/log/mysql/error.log 2>&1 &
fi

log "Starting Apache..."
if ! sudo service apache2 restart; then
  log "Service restart failed, trying apache2ctl..."
  if ! sudo apache2ctl start; then
    log "apache2ctl start failed, trying direct apache2 -k start..."
    sudo /usr/sbin/apache2 -k start || true
  fi
fi

# --- Start hooks (optional, each start/attach) ---
START_DIR="${WORKSPACE}/.devcontainer/sbin/start.sh.d"
mkdir -p "$START_DIR"
if [ -d "$START_DIR" ]; then
  for SCRIPT in "$START_DIR"/*; do
    [ -f "$SCRIPT" ] || continue
    log "Sourcing start hook: ${SCRIPT}"
    set +e
    . "$SCRIPT"
    status=$?
    set -e
    [ "$status" -eq 0 ] || log "Start hook ${SCRIPT} exited with status $status; continuing"
  done
fi

log "Start phase done. Services are up."
