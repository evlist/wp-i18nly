#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

set -euo pipefail

log() { printf "[merge-env] %s\n" "$*"; }
die() { printf "[merge-env:ERROR] %s\n" "$*" >&2; exit 1; }

DEVCONTAINER=${PWD}/.devcontainer
DEFAULTENV=${DEVCONTAINER}/.cs_env
ENVDIR=${DEVCONTAINER}/.cs_env.d
TMP=${DEVCONTAINER}/tmp
MERGEDENV=${TMP}/.cs_env.merged

mkdir -p $TMP
rm -f $MERGEDENV

# First: merge .devcontainer/.cs_env if it exists
if [ -f "$DEFAULTENV" ]; then
    log "Copying $DEFAULTENV"
    echo "# Copied from $DEFAULTENV:" >> $MERGEDENV
    cat "$DEFAULTENV" >> $MERGEDENV
    echo >> $MERGEDENV
fi

# Second: merge all files in .devcontainer/.cs_env.d/ in alphabetical order
mkdir -p "$ENVDIR"
if [ -d "$ENVDIR" ]; then
    for FILE in "$ENVDIR"/*; do
        if [ -f "$FILE" ]; then
            log "Copying $FILE"
            echo "# Copied from $FILE:" >> $MERGEDENV
            cat "$FILE" >> $MERGEDENV
            echo >> $MERGEDENV
        fi
    done
fi
