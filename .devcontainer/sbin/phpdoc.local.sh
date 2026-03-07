#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT
# TODO: install inotify-tools (requires universe)

while inotifywait -r -e modify,create,delete plugin/includes/; do
  echo "Regenerating documentation..."
  php /tmp/phpDocumentor.phar --config /workspaces/wp-i18nly/phpdoc.xml
done