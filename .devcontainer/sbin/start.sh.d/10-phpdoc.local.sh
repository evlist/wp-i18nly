#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

#sudo apt-get install inotify-tools
curl -fsSL -o /tmp/phpDocumentor.phar https://phpdoc.org/phpDocumentor.phar
php /tmp/phpDocumentor.phar --config /workspaces/wp-i18nly/phpdoc.xml
nohup php -S 0.0.0.0:8080 -t /workspaces/wp-i18nly/build/docs/php &
#nohup /workspaces/wp-i18nly/.devcontainer/sbin/phpdoc.local.sh &

