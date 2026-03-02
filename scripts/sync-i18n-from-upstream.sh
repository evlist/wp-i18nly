#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later

set -euo pipefail

MODE="sync"
FORCE=false

for arg in "$@"; do
    case "$arg" in
        --check)
            MODE="check"
            ;;
        --force)
            FORCE=true
            ;;
        *)
            echo "Unknown option: $arg" >&2
            echo "Usage: bash scripts/sync-i18n-from-upstream.sh [--check] [--force]" >&2
            exit 1
            ;;
    esac
done

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
UPSTREAM_REPO="https://github.com/wp-cli/i18n-command.git"
TARGET_DIR="$ROOT_DIR/plugin/third-party/wp-cli"
SRC_DIR="$TARGET_DIR/src"
SYNC_LOG="$TARGET_DIR/SYNC-LOG.md"
UPSTREAM_INFO="$TARGET_DIR/upstream-info.json"
TEMP_DIR="$(mktemp -d)"

cleanup() {
    rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

echo "Syncing wp-cli/i18n-command"
echo "Upstream: $UPSTREAM_REPO"
echo "Target:   $SRC_DIR"

git clone --depth=1 "$UPSTREAM_REPO" "$TEMP_DIR/i18n-command" >/dev/null 2>&1 || {
    echo "Failed to clone upstream repository" >&2
    exit 1
}

cd "$TEMP_DIR/i18n-command"
COMMIT="$(git rev-parse HEAD | cut -c1-7)"
COMMIT_FULL="$(git rev-parse HEAD)"
COMMIT_DATE="$(git log -1 --format=%ai)"
COMMIT_MSG="$(git log -1 --format=%B | head -1)"
cd - >/dev/null

CACHED_COMMIT=""
if [[ -f "$UPSTREAM_INFO" ]]; then
    CACHED_COMMIT="$(python3 - "$UPSTREAM_INFO" <<'PY'
import json
import sys

try:
    with open(sys.argv[1], encoding='utf-8') as handle:
        data = json.load(handle)
    print(data.get('commit', ''))
except Exception:
    print('')
PY
)"
fi

if [[ "$MODE" == "check" ]]; then
    if [[ "$CACHED_COMMIT" == "$COMMIT_FULL" ]]; then
        echo "Up-to-date. No sync needed."
        exit 0
    fi

    current_short="${CACHED_COMMIT:0:7}"
    [[ -z "$current_short" ]] && current_short="none"
    echo "Update available. Upstream: $COMMIT (current: $current_short)"
    exit 2
fi

if [[ "$CACHED_COMMIT" == "$COMMIT_FULL" && "$FORCE" != true ]]; then
    echo "Already at latest commit ($COMMIT). Use --force to re-sync."
    exit 0
fi

rm -rf "$SRC_DIR"
mkdir -p "$SRC_DIR"
cp -r "$TEMP_DIR/i18n-command/src"/* "$SRC_DIR/"
cp "$TEMP_DIR/i18n-command/upstream-package.json" "$TARGET_DIR/upstream-package.json" 2>/dev/null || true

cat > "$UPSTREAM_INFO" <<EOF
{
  "upstream": "https://github.com/wp-cli/i18n-command",
  "last_sync": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "commit": "$COMMIT_FULL",
  "commit_short": "$COMMIT",
  "commit_date": "$COMMIT_DATE",
  "commit_message": "$COMMIT_MSG",
  "notes": "Code copied from wp-cli/i18n-command/src"
}
EOF

cat >> "$SYNC_LOG" <<EOF

### $(date '+%Y-%m-%d %H:%M:%S')
- **Commit**: $COMMIT ($COMMIT_FULL)
- **Commit date**: $COMMIT_DATE
- **Message**: $COMMIT_MSG
- **Files copied**: $(find "$SRC_DIR" -type f | wc -l)
- **Status**: ✅ Synced successfully

EOF

echo "Sync complete."
echo "- Commit: $COMMIT"
echo "- Files:  $(find "$SRC_DIR" -type f | wc -l)"
echo "- Meta:   $UPSTREAM_INFO"
echo "- Log:    $SYNC_LOG"