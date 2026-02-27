#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

# Bootstrap hook: Plugin installation and activation
#
# This script is sourced by bootstrap.sh after WordPress core setup.
# It inherits all environment variables ($PLUGIN_SLUG, $WP_PLUGINS, etc.)
# and functions (log, die, wp) from the parent script.
#
# Naming convention for bootstrap hooks (provided by scion):
#   10-*.sh: Shell environment setup (aliases, functions)
#   20-*.sh: WordPress extensions (plugins, themes)
#
# For your own customizations, use .local.sh suffix to prevent overwriting:
#   25-themes.local.sh: Custom theme linking and activation
#   30-*.local.sh: Content import and configuration
#   40-*.local.sh: Custom WP-CLI commands
#   50-*.local.sh: Final tweaks and customization
#
# To disable plugin installation: leave PLUGIN_SLUG, PLUGIN_DIR, and WP_PLUGINS
# empty in your environment files. Don't modify or delete this file (it's managed
# by the scion and will be overwritten during upgrades).

log "Linking workspace plugin..."
sudo mkdir -p "$DOCROOT/wp-content/plugins" "$DOCROOT/wp-content"
if [ -d "$WORKSPACE/$PLUGIN_DIR" ]; then
    sudo ln -sfn "$WORKSPACE/$PLUGIN_DIR" "$DOCROOT/wp-content/plugins/$PLUGIN_SLUG"
    # Activate the plugin to test
    wp plugin activate $PLUGIN_SLUG
fi

# Example: WP_PLUGINS="akismet, jetpack@12.4, https://downloads.wordpress.org/plugin/wp-mail-smtp.latest-stable.zip"
if [ -n "${WP_PLUGINS:-}" ]; then
  IFS=',' read -r -a _plugins <<< "$WP_PLUGINS"
  for raw in "${_plugins[@]}"; do
    plugin="$(echo "$raw" | xargs)"   # trim
    [ -z "$plugin" ] && continue

    # Version support: slug@x.y.z (skip if it's a URL/ZIP)
    version=""
    if [[ "$plugin" != http*://* && "$plugin" != *.zip && "$plugin" == *@* ]]; then
      version="${plugin##*@}"
      plugin="${plugin%%@*}"
    fi

    if [[ "$plugin" == http*://* || "$plugin" == *.zip ]]; then
      # URL or ZIP install
      wp plugin install "$plugin" --activate || echo "Failed to install from $plugin"
    else
      if wp plugin is-installed "$plugin"; then
        # Already installed: activate, and optionally align to requested version
        wp plugin activate "$plugin" || echo "Failed to activate $plugin"
        if [ -n "$version" ]; then
          wp plugin update "$plugin" --version="$version" || echo "Failed to update $plugin to $version"
        fi
      else
        # Fresh install (with version if provided)
        if [ -n "$version" ]; then
          wp plugin install "$plugin" --version="$version" --activate || echo "Failed to install $plugin@$version"
        else
          wp plugin install "$plugin" --activate || echo "Failed to install $plugin"
        fi
      fi
    fi
  done
fi