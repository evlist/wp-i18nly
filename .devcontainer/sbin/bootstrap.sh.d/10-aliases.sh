#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

# Add aliases to be used in codespace terminals
echo 'alias graft="${CODESPACE_VSCODE_FOLDER}/.devcontainer/bin/graft.sh"' >> ~/.bash_aliases
echo 'alias upgrade-scion="graft upgrade"' >> ~/.bash_aliases
echo 'alias export-scion="graft export"' >> ~/.bash_aliases
