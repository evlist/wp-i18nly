#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

# Install PHP_CodeSniffer and WordPress Coding Standards

log() { printf "[bootstrap:phpcs] %s\n" "$*"; }

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

# Install phpcs and wpcs globally if not already installed
if ! command -v phpcs >/dev/null 2>&1; then
  log "Installing PHP_CodeSniffer and WordPress Coding Standards..."
  
  # Allow the composer installer plugin
  composer global config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
  
  # Install phpcs and wpcs (the plugin will auto-register the standards)
  composer global require --quiet squizlabs/php_codesniffer:^3.7
  composer global require --quiet wp-coding-standards/wpcs:^3.0
  
  # Add composer bin to PATH
  COMPOSER_BIN="$HOME/.config/composer/vendor/bin"
  [ -d "$HOME/.composer/vendor/bin" ] && COMPOSER_BIN="$HOME/.composer/vendor/bin"
  
  echo "export PATH=\"$COMPOSER_BIN:\$PATH\"" >> ~/.bash_aliases
  export PATH="$COMPOSER_BIN:$PATH"
  
  # Set WordPress as default standard
  "$COMPOSER_BIN/phpcs" --config-set default_standard WordPress
  
  log "PHP_CodeSniffer and WordPress Coding Standards installed"
  log "Available standards: $("$COMPOSER_BIN/phpcs" -i)"
else
  log "PHP_CodeSniffer already installed"
fi
