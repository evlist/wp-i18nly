#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later

# Add aliases to be used in codespace terminals
echo 'alias phpcs="phpcs --standard=.vscode/phpcs.xml --warning-severity=1"' >> ~/.bash_aliases