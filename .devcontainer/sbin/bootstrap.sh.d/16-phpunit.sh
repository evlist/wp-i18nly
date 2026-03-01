#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

# Install PHPUnit globally (for local development hooks)

log() { printf "[bootstrap:phpunit] %s\n" "$*"; }

# Check if composer is installed
if ! command -v composer >/dev/null 2>&1; then
  log "Installing Composer..."
  EXPECTED_CHECKSUM="$(wget -q -O - https://composer.github.io/installer.sig)"
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

  if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    log "ERROR: Invalid Composer installer checksum"
    rm composer-setup.php
    exit 1
  fi

  sudo php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
  rm composer-setup.php
  log "Composer installed successfully"
else
  log "Composer already installed"
fi

# Install phpunit globally if not already installed
if ! command -v phpunit >/dev/null 2>&1; then
  log "Installing PHPUnit globally..."
  composer global require --quiet phpunit/phpunit:^11

  COMPOSER_BIN="$HOME/.config/composer/vendor/bin"
  [ -d "$HOME/.composer/vendor/bin" ] && COMPOSER_BIN="$HOME/.composer/vendor/bin"

  if ! grep -q "${COMPOSER_BIN}:\$PATH" ~/.bash_aliases 2>/dev/null; then
    echo "export PATH=\"$COMPOSER_BIN:\$PATH\"" >> ~/.bash_aliases
  fi
  export PATH="$COMPOSER_BIN:$PATH"

  if command -v phpunit >/dev/null 2>&1; then
    log "PHPUnit installed: $(phpunit --version | head -n1)"
  else
    log "ERROR: PHPUnit installation seems to have failed"
    exit 1
  fi
else
  log "PHPUnit already installed: $(phpunit --version | head -n1)"
fi
