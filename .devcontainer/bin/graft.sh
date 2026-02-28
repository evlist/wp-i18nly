#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

# graft.sh -- apply a "scion" devcontainer/.vscode/.github into a "stock" repository
#
# Usage:
#   .devcontainer/bin/graft.sh [install|export|upgrade] [--scion <scion>] [--stock <stock>]
#                               [--tmp <dir>] [--target-stock-branch <name>]
#                               [--dry-run] [--non-interactive] [--push] [--debug]
#
set -euo pipefail

info()  { printf '\033[1;34m[INFO]\033[0m %s\n' "$*" >&2; }
warn()  { printf '\033[1;33m[WARN]\033[0m %s\n' "$*" >&2; }
err()   { printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

# ---- debug facility (minimal) ----
DEBUG=false
debug() { [ "${DEBUG:-false}" = "true" ] && printf '\033[1;35m[DEBUG]\033[0m %s\n' "$*" >&2 || true; }

# ---- defaults ----
NON_INTERACTIVE=false
DRY_RUN=false
TMP_BASE_DEFAULT="/workspaces"
BRANCH_PREFIX="graft"
TARGET_STOCK_BRANCH=""

# Track whether we needed to unset GITHUB_TOKEN to push
PUSH_SUCCEEDED_WITHOUT_TOKEN=false
INITIAL_GITHUB_TOKEN_PRESENT=false
if [ -n "${GITHUB_TOKEN:-}" ]; then INITIAL_GITHUB_TOKEN_PRESENT=true; fi

# Open stable prompt FD from /dev/tty when available
PROMPT_FD=""
if [ -e /dev/tty ]; then
  # shellcheck disable=SC2034
  exec 3</dev/tty && PROMPT_FD=3 || PROMPT_FD=""
fi

# ---- prompt helpers (use PROMPT_FD when available) ----
_prompt_print() { printf '%s' "$1" >&2; }

_prompt_read() {
  local prompt="$1" __out="$2"
  local ans=""
  _prompt_print "$prompt "
  if [ -n "$PROMPT_FD" ]; then
    # try to read from PROMPT_FD; if that fails fall back to stdin
    if ! read -r -u "$PROMPT_FD" ans 2>/dev/null; then
      read -r ans || true
      debug "_prompt_read: fell back to stdin, got: '$ans'"
    else
      debug "_prompt_read: read from PROMPT_FD, got: '$ans'"
    fi
  else
    read -r ans || true
    debug "_prompt_read: PROMPT_FD unset, read from stdin, got: '$ans'"
  fi
  ans="$(printf '%s' "$ans" | awk '{$1=$1;print}')"
  printf -v "$__out" "%s" "$ans"
}

# prompt_confirm(prompt_text, default)
#   default: "yes" or "no"
# The function prints the prompt with the canonical suffix:
#   default=yes -> " ... [Yn]"
#   default=no  -> " ... [yN]"
# Returns 0 for yes, 1 for no.
prompt_confirm() {
  local prompt="${1:-Proceed?}"
  local default="${2:-no}"
  if [ "$NON_INTERACTIVE" = "true" ]; then
    debug "prompt_confirm: non-interactive => choose default"
    if [[ "$default" =~ ^([yY][eE][sS])$ ]]; then return 0; else return 1; fi
  fi
  local suffix
  case "$default" in
    yes|YES|Yes) suffix='[Y/n]' ;;
    no|NO|No)    suffix='[y/N]' ;;
    *) suffix='[y/N]' ;;
  esac

  local raw=""
  _prompt_read "${prompt} ${suffix}" raw
  printf '\n' >&2
  debug "prompt_confirm: raw read -> '$raw' (default=${default})"

  raw="${raw:-}"
  local first="${raw:0:1}"
  case "$first" in
    [Yy]) return 0 ;;
    [Nn]) return 1 ;;
    '')  # empty => choose default
      if [[ "$default" =~ ^([yY][eE][sS])$ ]]; then return 0; else return 1; fi
      ;;
    *)   # unknown input: use default
      if [[ "$default" =~ ^([yY][eE][sS])$ ]]; then return 0; else return 1; fi
      ;;
  esac
}

prompt_choice() {
  local prompt="$1"; shift
  local def="$1"; shift
  local -a opts=("$@"); local i=1
  {
    printf '%s\n' "$prompt"
    for o in "${opts[@]}"; do
      if [ "$i" -eq "$def" ]; then
        printf '  %d) %s [default]\n' "$i" "$o"
      else
        printf '  %d) %s\n' "$i" "$o"
      fi
      i=$((i+1))
    done
  } >&2

  local choice=""
  _prompt_read "Enter choice [${def}]:" choice
  if [ -z "$choice" ]; then
    printf '%s' "$def"
    return 0
  fi
  if ! printf '%s\n' "$choice" | grep -qE '^[0-9]+$'; then
    printf '%s' "$def"
    return 0
  fi
  printf '%s' "$choice"
  return 0
}

sanitize_name() { local s="$1"; echo "${s//[^A-Za-z0-9._-]/_}"; }

# ---- gh detection ----
GH_AVAILABLE=false
detect_gh() { command -v gh >/dev/null 2>&1 && GH_AVAILABLE=true || GH_AVAILABLE=false; }

# ---- parse repo spec ----
REF=""

urldecode_ref() {
  local value="${1//+/ }"
  printf '%b' "${value//%/\\x}"
}

resolve_github_tree_ref() {
  local owner="$1" repo="$2" raw_ref="$3"
  local decoded_ref=""
  decoded_ref="$(urldecode_ref "$raw_ref")"

  # Fast path: no slash, no ambiguity.
  if [[ "$decoded_ref" != */* ]]; then
    printf '%s\n' "$decoded_ref"
    return 0
  fi

  local remote_url="https://github.com/${owner}/${repo}.git"
  local refs
  refs="$( (git ls-remote --heads --tags "$remote_url" 2>/dev/null || true) | awk '{print $2}' | sed -E 's#^refs/(heads|tags)/##' )"

  # If remote lookup fails, keep the decoded tail as-is.
  if [ -z "$refs" ]; then
    printf '%s\n' "$decoded_ref"
    return 0
  fi

  local candidate="$decoded_ref"
  while [ -n "$candidate" ]; do
    if printf '%s\n' "$refs" | grep -Fxq "$candidate"; then
      printf '%s\n' "$candidate"
      return 0
    fi
    if [[ "$candidate" != */* ]]; then
      break
    fi
    candidate="${candidate%/*}"
  done

  printf '%s\n' "$decoded_ref"
}

parse_repo_spec() {
  local spec="$1"
  REF=""
  if [ -d "$spec" ]; then
    printf '%s\n' "$(cd "$spec" && pwd)"
    return 0
  fi
  if [[ "$spec" =~ ^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)@(.+)$ ]]; then
    REF="${BASH_REMATCH[3]}"
    printf '%s/%s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
    return 0
  fi
  if [[ "$spec" =~ ^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$ ]]; then
    printf '%s/%s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
    return 0
  fi
  if [[ "$spec" =~ ^git@github\.com:([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)(\.git)?$ ]]; then
    printf '%s/%s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
    return 0
  fi
  if [[ "$spec" =~ github\.com[:/]+([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)(\.git)?(/tree/(.+))? ]]; then
    if [[ -n "${BASH_REMATCH[5]:-}" ]]; then
      REF="$(resolve_github_tree_ref "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}" "${BASH_REMATCH[5]}")"
    fi
    printf '%s/%s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
    return 0
  fi
  return 1
}

# ---- resolve local path to canonical GitHub ID ----
resolve_to_canonical_id() {
  local spec="$1"
  
  # If it's a directory with a git remote, derive the canonical repo ID
  if [ -d "$spec/.git" ]; then
    local origin_url origin_canon
    origin_url="$(git -C "$spec" config --get remote.origin.url 2>/dev/null || true)"
    if [ -n "$origin_url" ]; then
      if origin_canon="$(parse_repo_spec "$origin_url")"; then
        debug "Resolved '$spec' to canonical ID: $origin_canon"
        printf '%s\n' "$origin_canon"
        return 0
      fi
    fi
  fi
  
  # Otherwise return as-is (already a GitHub ID or URL)
  printf '%s\n' "$spec"
}

# ---- clone remote into tmp_base/<owner>/<repo> (no timestamp) ----
clone_remote_into_tmp() {
  local repo="$1"; local tmp_base="$2"; local ref="${3:-}"
  local owner="${repo%%/*}"; local rname="${repo##*/}"
  local dest="${tmp_base%/}/${owner}/${rname}"
  if [ -d "$dest" ]; then
    if [ "$NON_INTERACTIVE" = "true" ]; then
      rm -rf "$dest"
      info "Removed existing $dest (non-interactive)"
    else
      if prompt_confirm "Destination $dest already exists. Remove and re-clone?" yes; then
        rm -rf "$dest"
        info "Removed existing $dest"
      else
        err "Destination $dest exists; aborting to avoid overwriting. Remove manually or run with --tmp to specify another base."
      fi
    fi
  fi
  mkdir -p "$(dirname "$dest")"
  if [ -n "$ref" ]; then
    git clone --depth=1 --branch "$ref" "https://github.com/${repo}.git" "$dest"
  else
    git clone --depth=1 "https://github.com/${repo}.git" "$dest"
  fi
  printf '%s\n' "$dest"
}

# ---- preflight .gitignore scan ----
preflight_gitignore_from_scion() {
  local src="$1"
  info "Preflight: scanning .gitignore impact against scion paths (before copy)"
  if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    warn "Not inside a Git work tree; skipping ignore scan."
    return 0
  fi
  if [ ! -d "$src/.vscode" ] && [ ! -d "$src/.devcontainer" ] && [ ! -d "$src/.github" ]; then
    info "No scion .vscode, .devcontainer or .github found; skipping ignore scan."
    return 0
  fi

  build_paths_stream() {
    local base="$src"
    [ -d "$src/.vscode" ] && find "$src/.vscode" -type d -print0 2>/dev/null | while IFS= read -r -d '' p; do printf '%s\0' "${p#"$base/"}"; done
    [ -d "$src/.vscode" ] && find "$src/.vscode" -type f -print0 2>/dev/null | while IFS= read -r -d '' p; do printf '%s\0' "${p#"$base/"}"; done
    [ -d "$src/.devcontainer" ] && find "$src/.devcontainer" -type d -print0 2>/dev/null | while IFS= read -r -d '' p; do printf '%s\0' "${p#"$base/"}"; done
    [ -d "$src/.devcontainer" ] && find "$src/.devcontainer" -type f -print0 2>/dev/null | while IFS= read -r -d '' p; do printf '%s\0' "${p#"$base/"}"; done
    [ -d "$src/.github" ] && find "$src/.github" -type d -print0 2>/dev/null | while IFS= read -r -d '' p; do printf '%s\0' "${p#"$base/"}"; done
    [ -d "$src/.github" ] && find "$src/.github" -type f -print0 2>/dev/null | while IFS= read -r -d '' p; do printf '%s\0' "${p#"$base/"}"; done
  }

  local ignored_lines=""
  if git check-ignore -h 2>&1 | grep -q -- "--stdin"; then
    ignored_lines="$(
      build_paths_stream \
      | git check-ignore -v --stdin -z 2>/dev/null \
      | tr '\0' '\n' || true
    )"
  else
    local tmp_list tmp_out
    tmp_list="$(mktemp)"; tmp_out="$(mktemp)"
    build_paths_stream | tr '\0' '\n' > "$tmp_list"
    while IFS= read -r rel; do
      [ -z "$rel" ] && continue
      git check-ignore -v "$rel" || true
    done < "$tmp_list" > "$tmp_out"
    ignored_lines="$(cat "$tmp_out" 2>/dev/null || true)"
    rm -f "$tmp_list" "$tmp_out"
  fi

  if [ -n "$ignored_lines" ]; then
    info "Ignored scion destinations detected (pre-copy):"
    while IFS= read -r line; do
      if printf '%s' "$line" | grep -q $'\t'; then
        rule="${line%%$'\t'*}"; path="${line#*$'\t'}"
        info "  $path"; info "    rule: $rule"
      else
        info "  $line"
      fi
    done <<< "$ignored_lines"
    if [ "$DRY_RUN" = "true" ]; then
      info "Dry-run: please fix .gitignore. Continuing without changes."
      return 0
    else
      err "Aborting: .gitignore would ignore files needed under .vscode/.devcontainer/.github."
    fi
  else
    info "No problematic ignores detected for scion .vscode/.devcontainer/.github destinations."
  fi
}

# ---- apply .devcontainer ----
apply_devcontainer() {
  local src="$1" dst="$2"
  if [ ! -d "$src/.devcontainer" ]; then
    info "Scion has no .devcontainer; skipping."
    return 0
  fi
  command -v rsync >/dev/null 2>&1 || err "rsync required; install rsync."
  local opts=(-a --delete)
  [ "$DRY_RUN" = "true" ] && opts+=(--dry-run)
  local excludes=(--exclude "tmp/" --exclude "var/" --exclude "*.local" --exclude "*.local.*")
  info "Syncing .devcontainer -> $dst/.devcontainer"
  rsync "${opts[@]}" "${excludes[@]}" "$src/.devcontainer/" "$dst/.devcontainer/"
}

# ---- apply .vscode with scion snapshot handling ----
apply_vscode_baseline() {
  local src="$1" dst="$2"
  if [ ! -d "$src/.vscode" ]; then
    info "Scion has no .vscode; skipping."
    return 0
  fi
  info "Updating .vscode (scion snapshots: .orig; interactive prompts)"
  [ "$DRY_RUN" = "true" ] || mkdir -p "$dst/.vscode"
  umask 022

  while IFS= read -r -d '' uf; do
    local rel dest base dist
    rel="${uf#"$src/"}"
    dest="$dst/$rel"
    base="${dest}.orig"
    dist="${dest}.dist"

    [ "$DRY_RUN" = "true" ] || mkdir -p "$(dirname "$dest")"

    if [ ! -f "$dest" ]; then
      if [ "$DRY_RUN" = "true" ]; then
        info "[DRY] add $rel"
      else
        info "add $rel"
        install -m 0644 "$uf" "$dest"
        install -m 0644 "$uf" "$base"
      fi
      continue
    fi

    if [ ! -f "$base" ]; then
      if [ "$DRY_RUN" = "true" ]; then
        info "[DRY] init scion (previous) (.orig) for $rel"
      else
        install -m 0644 "$dest" "$base"
      fi
    fi

    local same_local_scion=0 same_local_prev_scion=0 same_prev_scion_new_scion=0
    cmp -s "$dest" "$uf" && same_local_scion=1 || true
    cmp -s "$dest" "$base" && same_local_prev_scion=1 || true
    cmp -s "$base" "$uf" && same_prev_scion_new_scion=1 || true

    if [ "$same_local_scion" -eq 1 ]; then
      info "keep (identical to scion) $rel"
      if [ "$same_prev_scion_new_scion" -eq 0 ]; then
        [ "$DRY_RUN" = "true" ] && info "[DRY] update scion (previous) (.orig) for $rel" || install -m 0644 "$uf" "$base"
      fi
      continue
    fi

    if [ "$same_local_prev_scion" -eq 1 ] && [ "$same_prev_scion_new_scion" -eq 0 ]; then
      [ "$DRY_RUN" = "true" ] && info "[DRY] replace (unmodified locally; scion changed) $rel" || { info "replace (unmodified locally; scion changed) $rel"; install -m 0644 "$uf" "$dest"; install -m 0644 "$uf" "$base"; }
      continue
    fi

    if [ "$NON_INTERACTIVE" = "true" ]; then
      [ "$DRY_RUN" = "true" ] && info "[DRY] save scion sample -> $dist" || { info "save scion sample to $dist; keeping local $rel"; install -m 0644 "$uf" "$dist"; }
      continue
    fi

    while :; do
      info ""
      info "Config file '$rel' differs."
      info "  d: local vs scion (new)"
      info "  2: local vs scion (previous) (.orig)"
      info "  3: scion (previous) vs scion (new)"
      info "Actions: y=replace, n=keep, b=backup+replace, u=save scion sample -> .dist, m=merge, r=revert to .orig, s=skip"

      _prompt_read "Choose [d/2/3/y/n/b/u/m/r/s]:" choice

      case "${choice:-s}" in
        d|D) udiff "$dest" "$uf"; continue ;;
        2) udiff "$dest" "$base"; continue ;;
        3) udiff "$base" "$uf"; continue ;;
        y|Y)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] replace $rel"; else install -m 0644 "$uf" "$dest"; install -m 0644 "$uf" "$base"; fi
          break
          ;;
        n|N) info "keep local $rel"; break ;;
        b|B)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] backup+replace $rel"; else bak="${dest}.bak.$(date +%Y%m%d%H%M%S)"; info "backup local to $(basename "$bak") and replace $rel"; cp -p "$dest" "$bak"; install -m 0644 "$uf" "$dest"; install -m 0644 "$uf" "$base"; fi
          break
          ;;
        u|U)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] save scion sample -> $dist"; else info "save scion sample to $dist; keeping local $rel"; install -m 0644 "$uf" "$dist"; fi
          break
          ;;
        m|M)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] attempt merge $rel"; else
            merged="$(mktemp)"
            if command -v git >/dev/null 2>&1; then git merge-file -p "$dest" "$base" "$uf" > "$merged" || true; else diff3 -m "$dest" "$base" "$uf" > "$merged" || true; fi
            info "---- merged preview (first 60 lines) ----"
            head -n 60 "$merged" || true
            if prompt_confirm "Apply merged result to $rel?" no; then
              install -m 0644 "$merged" "$dest"
              install -m 0644 "$uf" "$base"
              info "merged applied"
              rm -f "$merged"
              break
            else
              info "merge discarded"
              rm -f "$merged"
              continue
            fi
          fi
          ;;
        r|R)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] revert $rel <- .orig"; else bak="${dest}.bak.$(date +%Y%m%d%H%M%S)"; info "revert local to scion (previous); backup to $(basename "$bak")"; cp -p "$dest" "$bak"; install -m 0644 "$base" "$dest"; fi
          break
          ;;
        s|S|*) info "skip $rel"; break ;;
      esac
    done
  done < <(find "$src/.vscode" -type f -print0)
}

# ---- apply .github with scion snapshot handling ----
apply_github_baseline() {
  local src="$1" dst="$2"
  if [ ! -d "$src/.github" ]; then
    info "Scion has no .github; skipping."
    return 0
  fi
  info "Updating .github (scion snapshots: .orig; interactive prompts)"
  [ "$DRY_RUN" = "true" ] || mkdir -p "$dst/.github"
  umask 022

  while IFS= read -r -d '' uf; do
    local rel dest base dist
    rel="${uf#"$src/"}"
    dest="$dst/$rel"
    base="${dest}.orig"
    dist="${dest}.dist"

    [ "$DRY_RUN" = "true" ] || mkdir -p "$(dirname "$dest")"

    if [ ! -f "$dest" ]; then
      if [ "$DRY_RUN" = "true" ]; then
        info "[DRY] add $rel"
      else
        info "add $rel"
        install -m 0644 "$uf" "$dest"
        install -m 0644 "$uf" "$base"
      fi
      continue
    fi

    if [ ! -f "$base" ]; then
      if [ "$DRY_RUN" = "true" ]; then
        info "[DRY] init scion (previous) (.orig) for $rel"
      else
        install -m 0644 "$dest" "$base"
      fi
    fi

    local same_local_scion=0 same_local_prev_scion=0 same_prev_scion_new_scion=0
    cmp -s "$dest" "$uf" && same_local_scion=1 || true
    cmp -s "$dest" "$base" && same_local_prev_scion=1 || true
    cmp -s "$base" "$uf" && same_prev_scion_new_scion=1 || true

    if [ "$same_local_scion" -eq 1 ]; then
      info "keep (identical to scion) $rel"
      if [ "$same_prev_scion_new_scion" -eq 0 ]; then
        [ "$DRY_RUN" = "true" ] && info "[DRY] update scion (previous) (.orig) for $rel" || install -m 0644 "$uf" "$base"
      fi
      continue
    fi

    if [ "$same_local_prev_scion" -eq 1 ] && [ "$same_prev_scion_new_scion" -eq 0 ]; then
      [ "$DRY_RUN" = "true" ] && info "[DRY] replace (unmodified locally; scion changed) $rel" || { info "replace (unmodified locally; scion changed) $rel"; install -m 0644 "$uf" "$dest"; install -m 0644 "$uf" "$base"; }
      continue
    fi

    if [ "$NON_INTERACTIVE" = "true" ]; then
      [ "$DRY_RUN" = "true" ] && info "[DRY] save scion sample -> $dist" || { info "save scion sample to $dist; keeping local $rel"; install -m 0644 "$uf" "$dist"; }
      continue
    fi

    while :; do
      info ""
      info "Config file '$rel' differs."
      info "  d: local vs scion (new)"
      info "  2: local vs scion (previous) (.orig)"
      info "  3: scion (previous) vs scion (new)"
      info "Actions: y=replace, n=keep, b=backup+replace, u=save scion sample -> .dist, m=merge, r=revert to .orig, s=skip"

      _prompt_read "Choose [d/2/3/y/n/b/u/m/r/s]:" choice

      case "${choice:-s}" in
        d|D) udiff "$dest" "$uf"; continue ;;
        2) udiff "$dest" "$base"; continue ;;
        3) udiff "$base" "$uf"; continue ;;
        y|Y)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] replace $rel"; else install -m 0644 "$uf" "$dest"; install -m 0644 "$uf" "$base"; fi
          break
          ;;
        n|N) info "keep local $rel"; break ;;
        b|B)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] backup+replace $rel"; else bak="${dest}.bak.$(date +%Y%m%d%H%M%S)"; info "backup local to $(basename "$bak") and replace $rel"; cp -p "$dest" "$bak"; install -m 0644 "$uf" "$dest"; install -m 0644 "$uf" "$base"; fi
          break
          ;;
        u|U)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] save scion sample -> $dist"; else info "save scion sample to $dist; keeping local $rel"; install -m 0644 "$uf" "$dist"; fi
          break
          ;;
        m|M)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] attempt merge $rel"; else
            merged="$(mktemp)"
            if command -v git >/dev/null 2>&1; then git merge-file -p "$dest" "$base" "$uf" > "$merged" || true; else diff3 -m "$dest" "$base" "$uf" > "$merged" || true; fi
            info "---- merged preview (first 60 lines) ----"
            head -n 60 "$merged" || true
            if prompt_confirm "Apply merged result to $rel?" no; then
              install -m 0644 "$merged" "$dest"
              install -m 0644 "$uf" "$base"
              info "merged applied"
              rm -f "$merged"
              break
            else
              info "merge discarded"
              rm -f "$merged"
              continue
            fi
          fi
          ;;
        r|R)
          if [ "$DRY_RUN" = "true" ]; then info "[DRY] revert $rel <- .orig"; else bak="${dest}.bak.$(date +%Y%m%d%H%M%S)"; info "revert local to scion (previous); backup to $(basename "$bak")"; cp -p "$dest" "$bak"; install -m 0644 "$base" "$dest"; fi
          break
          ;;
        s|S|*) info "skip $rel"; break ;;
      esac
    done
  done < <(find "$src/.github" -type f -print0)
}

# ---- helpers ----
udiff() { local a="$1" b="$2"; if command -v git >/dev/null 2>&1; then git diff --no-index -- "$a" "$b" || true; else diff -u "$a" "$b" || true; fi }

commit_changes() {
  local repo_dir="$1" scion_desc="$2"
  (cd "$repo_dir"
    if git status --porcelain | grep -q .; then
      git add -A
      git commit -m "Graft: apply scion ${scion_desc}"
      return 0
    else
      info "No changes to commit."
      return 1
    fi
  )
}

# ---- push logic (use gh helper; unset GITHUB_TOKEN so user auth is used) ----
push_branch() {
  local repo_dir="$1" branch="$2"
  info "Pushing branch $branch..."

  if env -u GITHUB_TOKEN git -C "$repo_dir" -c credential.helper='!gh auth git-credential' push -u origin HEAD 2> >(tee /tmp/graft_push_err.$$ >&2); then
    info "Pushed HEAD -> origin (via gh credential helper)"
    PUSH_SUCCEEDED_WITHOUT_TOKEN=true
    rm -f /tmp/graft_push_err.$$ 2>/dev/null || true
    return 0
  else
    local push_err
    push_err="$(cat /tmp/graft_push_err.$$ 2>/dev/null || true)"
    rm -f /tmp/graft_push_err.$$ 2>/dev/null || true
  fi

  if printf '%s' "$push_err" | grep -qiE 'rejected|non-fast-forward|fetch first'; then
    warn "Push rejected (remote branch non-fast-forward)."
    if [ "$NON_INTERACTIVE" = "true" ]; then
      warn "Non-interactive mode: not attempting force update. Manual resolution required."
      return 1
    fi
    if prompt_confirm "Remote branch has conflicting commits. Attempt a safe force push (git push --force-with-lease)?" no; then
      if env -u GITHUB_TOKEN git -C "$repo_dir" -c credential.helper='!gh auth git-credential' push -u origin HEAD:"$branch" --force-with-lease; then
        info "Force-with-lease push succeeded."
        PUSH_SUCCEEDED_WITHOUT_TOKEN=true
        return 0
      else
        warn "Force-with-lease push failed."
      fi
    else
      info "Skipping force push as requested."
    fi
  fi

  if env -u GITHUB_TOKEN git -C "$repo_dir" push --set-upstream origin "$branch"; then
    info "Pushed to origin/$branch"
    PUSH_SUCCEEDED_WITHOUT_TOKEN=true
    return 0
  fi

  warn "Push failed."
  return 1
}

# ---- provenance ----
detect_repo_ref() {
  local repo_dir="$1"

  [ -d "$repo_dir/.git" ] || return 1

  local branch_ref=""
  branch_ref="$(git -C "$repo_dir" symbolic-ref --quiet --short HEAD 2>/dev/null || true)"
  if [ -n "$branch_ref" ]; then
    printf '%s\n' "$branch_ref"
    return 0
  fi

  local tag_ref=""
  tag_ref="$(git -C "$repo_dir" describe --tags --exact-match 2>/dev/null || true)"
  if [ -n "$tag_ref" ]; then
    printf '%s\n' "$tag_ref"
    return 0
  fi

  return 1
}

record_provenance_file() {
  local target_dir="$1" scion_spec="$2" scion_ref="$3" scion_commit="$4"

  # Resolve scion_spec to canonical GitHub ID if it's a local path
  local scion_canonical
  scion_canonical="$(resolve_to_canonical_id "$scion_spec")"
  
  # Build full scion spec with ref
  local scion_full="${scion_canonical}"
  [ -n "$scion_ref" ] && scion_full="${scion_full}@${scion_ref}"
  
  mkdir -p "$target_dir/.devcontainer/.cs_env.d"
  cat > "$target_dir/.devcontainer/.cs_env.d/graft.local.env" <<EOF
# SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

# Graft scion provenance - generated by graft.sh export
# This file tracks which scion version was grafted into this stock repository
#
# Format: owner/repo@ref or full URL
SCION=${scion_full}
SCION_COMMIT=${scion_commit}
SCION_INSTALLED_AT=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
EOF
  info "Scion provenance recorded at $target_dir/.devcontainer/.cs_env.d/graft.local.env"
}

# ---- main flow ----
print_usage_and_exit() {
  cat >&2 <<'EOF'
Usage: graft.sh [install|export|upgrade] [--scion <scion>] [--stock <stock>] [--tmp <dir>]
                [--target-stock-branch <name>] [--dry-run] [--non-interactive] [--push] [--debug]
EOF
  exit 2
}

VERB=""
if [ "$#" -gt 0 ]; then
  case "$1" in install|export|upgrade) VERB="$1"; shift || true ;; esac
fi

SCION_SPEC=""; STOCK_SPEC=""; TMP_BASE=""; TARGET_STOCK_BRANCH_ARG=""; PUSH_FLAG=false

while [ "$#" -gt 0 ]; do
  case "$1" in
    --scion) SCION_SPEC="$2"; shift 2 ;;
    --stock) STOCK_SPEC="$2"; shift 2 ;;
    --tmp) TMP_BASE="$2"; shift 2 ;;
    --target-stock-branch) TARGET_STOCK_BRANCH_ARG="$2"; shift 2 ;;
    --dry-run) DRY_RUN=true; shift ;;
    --non-interactive) NON_INTERACTIVE=true; shift ;;
    --push) PUSH_FLAG=true; shift ;;
    --debug) DEBUG=true; shift ;;
    -h|--help) print_usage_and_exit ;;
    *) err "Unknown arg: $1" ;;
  esac
done

if [ "$VERB" = "install" ]; then
  STOCK_SPEC="$PWD"
  [ -n "$SCION_SPEC" ] || SCION_SPEC="$UPSTREAM_SCION"
  [ -z "$TMP_BASE" ] && TMP_BASE="$(mktemp -d)"
fi
if [ "$VERB" = "export" ]; then
  [ -z "$SCION_SPEC" ] && SCION_SPEC="$PWD"
  [ -z "$STOCK_SPEC" ] && err "export requires --stock"
  [ -z "$TMP_BASE" ] && TMP_BASE="${TMP_BASE_DEFAULT:-/workspaces}"
fi
if [ "$VERB" = "upgrade" ]; then
  STOCK_SPEC="$PWD"
  if [ -z "$SCION_SPEC" ]; then
    if [ -f ".devcontainer/.cs_env.d/graft.local.env" ]; then
      # Source the graft environment file to get the scion reference
      source ".devcontainer/.cs_env.d/graft.local.env"
      SCION_SPEC="${SCION:-}"
    else
      err "No .devcontainer/.cs_env.d/graft.local.env; supply --scion"
    fi
  fi
  [ -z "$TMP_BASE" ] && TMP_BASE="${TMP_BASE_DEFAULT:-/workspaces}"
fi

if [ -z "$TMP_BASE" ]; then
  if [ -d "/workspaces" ]; then TMP_BASE="/workspaces"; else TMP_BASE="$(mktemp -d)"; fi
fi

if [ -n "$TARGET_STOCK_BRANCH_ARG" ]; then TARGET_STOCK_BRANCH="$TARGET_STOCK_BRANCH_ARG"; else TARGET_STOCK_BRANCH="${BRANCH_PREFIX}/$(date +%Y-%m-%dT%H-%M-%S)"; fi
if [ -z "$SCION_SPEC" ]; then
  # Try to read default scion from .devcontainer/.cs_env.d/graft.local.env
  if [ -f ".devcontainer/.cs_env.d/graft.local.env" ]; then
    source ".devcontainer/.cs_env.d/graft.local.env"
    SCION_SPEC="${SCION:-}"
  fi
  [ -z "$SCION_SPEC" ] && err "Cannot determine scion; supply --scion"
fi
# Handle --scion @ref shortcut (use default scion repo with different ref)
if [[ "$SCION_SPEC" =~ ^@(.+)$ ]]; then
  ref_only="${BASH_REMATCH[1]}"
  if [ -f ".devcontainer/.cs_env.d/graft.local.env" ]; then
    source ".devcontainer/.cs_env.d/graft.local.env"
    base_scion="${SCION:-}"
    # Strip existing @ref from base_scion
    base_scion="${base_scion%%@*}"
    SCION_SPEC="${base_scion}@${ref_only}"
    info "Resolved --scion @${ref_only} to ${SCION_SPEC}"
  else
    err "--scion @ref requires .devcontainer/.cs_env.d/graft.local.env with default SCION"
  fi
fi

if [ -z "$SCION_SPEC" ] || [ -z "$STOCK_SPEC" ]; then err "Both --scion and --stock must be specified (or use a verb)"; fi

detect_gh

if ! SCION_CANON="$(parse_repo_spec "$SCION_SPEC")"; then err "Could not parse scion spec: $SCION_SPEC"; fi
SCION_REF="${REF:-}"; SCION_IS_LOCAL=false
if [ -d "$SCION_SPEC" ]; then SCION_LOCAL_PATH="$(cd "$SCION_SPEC" && pwd)"; SCION_IS_LOCAL=true; else SCION_LOCAL_PATH=""; fi

if ! STOCK_CANON="$(parse_repo_spec "$STOCK_SPEC")"; then err "Could not parse stock spec: $STOCK_SPEC"; fi
STOCK_REF="${REF:-}"; STOCK_IS_LOCAL=false
if [ -d "$STOCK_SPEC" ]; then STOCK_LOCAL_PATH="$(cd "$STOCK_SPEC" && pwd)"; STOCK_IS_LOCAL=true; else STOCK_LOCAL_PATH=""; fi

info "Resolved scion: ${SCION_CANON}${SCION_REF:+@${SCION_REF}} ${SCION_IS_LOCAL:+(local)}"
info "Resolved stock: ${STOCK_CANON}${STOCK_REF:+@${STOCK_REF}} ${STOCK_IS_LOCAL:+(local)}"
info "Tmp base: $TMP_BASE"
info "Target stock branch: $TARGET_STOCK_BRANCH"
info "Dry-run: $DRY_RUN"
info "Non-interactive: $NON_INTERACTIVE"
[ "$PUSH_FLAG" = "true" ] && info "--push requested"
[ "${DEBUG:-false}" = "true" ] && debug "Debugging enabled"

# If scion is local and dirty, mention it and offer to reclone
if [ -n "${SCION_LOCAL_PATH:-}" ] && [ -d "$SCION_LOCAL_PATH/.git" ]; then
  scion_dirty_msg=""
  if [ -n "$(git -C "$SCION_LOCAL_PATH" status --porcelain 2>/dev/null || true)" ]; then scion_dirty_msg=" (local working tree has uncommitted changes)"; fi
  if [ "$NON_INTERACTIVE" != "true" ]; then
    if prompt_confirm "Scion is a local git repo at $SCION_LOCAL_PATH${scion_dirty_msg}. Re-clone scion from its origin remote into a clean tmp dir before graft?" no; then
      # Use resolve_to_canonical_id instead of manual conversion
      origin_canon="$(resolve_to_canonical_id "$SCION_LOCAL_PATH")"
      if [ "$origin_canon" != "$SCION_LOCAL_PATH" ]; then
        info "Re-cloning scion from $origin_canon into tmp for a clean copy..."
        SCION_LOCAL_PATH="$(clone_remote_into_tmp "$origin_canon" "$TMP_BASE" "$SCION_REF")"
        SCION_IS_LOCAL=true
      else
        warn "Could not resolve origin; using local path"
      fi
    fi
  else
    # non-interactive default: reclone if origin exists
    origin_canon="$(resolve_to_canonical_id "$SCION_LOCAL_PATH")"
    if [ "$origin_canon" != "$SCION_LOCAL_PATH" ]; then
      info "Non-interactive: re-cloning scion from origin into tmp..."
      SCION_LOCAL_PATH="$(clone_remote_into_tmp "$origin_canon" "$TMP_BASE" "$SCION_REF")"
      SCION_IS_LOCAL=true
    fi
  fi
fi

# clone scion/stock into tmp if needed
if [ "$SCION_IS_LOCAL" = "false" ]; then info "Cloning scion ${SCION_CANON} into tmp..."; SCION_LOCAL_PATH="$(clone_remote_into_tmp "$SCION_CANON" "$TMP_BASE" "$SCION_REF")"; fi
if [ "$STOCK_IS_LOCAL" = "false" ]; then info "Cloning stock ${STOCK_CANON} into tmp..."; STOCK_LOCAL_PATH="$(clone_remote_into_tmp "$STOCK_CANON" "$TMP_BASE" "$STOCK_REF")"; fi

info "Working in stock at $STOCK_LOCAL_PATH"
cd "$STOCK_LOCAL_PATH" || err "Cannot cd into stock working dir"
if ! git rev-parse --show-toplevel >/dev/null 2>&1; then err "Stock is not a git repository"; fi
DEFAULT_BRANCH="$(git remote show origin | sed -n 's/  HEAD branch: //p' || true)"; [ -z "$DEFAULT_BRANCH" ] && DEFAULT_BRANCH="main"

# branch creation (default YES)
if [ "$NON_INTERACTIVE" = "true" ]; then CREATE_BRANCH=true; else if prompt_confirm "Create and switch to new branch $TARGET_STOCK_BRANCH (off $DEFAULT_BRANCH)?" yes; then CREATE_BRANCH=true; else CREATE_BRANCH=false; fi; fi

if [ "$CREATE_BRANCH" = "true" ]; then
  git fetch origin >/dev/null 2>&1 || true
  if git rev-parse --verify "origin/$DEFAULT_BRANCH" >/dev/null 2>&1; then
    git checkout -b "$TARGET_STOCK_BRANCH" "origin/$DEFAULT_BRANCH"
  else
    git checkout -b "$TARGET_STOCK_BRANCH"
  fi
  info "On branch $(git rev-parse --abbrev-ref HEAD)"
fi

# run graft
preflight_gitignore_from_scion "$SCION_LOCAL_PATH"
apply_devcontainer "$SCION_LOCAL_PATH" "$STOCK_LOCAL_PATH"
apply_vscode_baseline "$SCION_LOCAL_PATH" "$STOCK_LOCAL_PATH"
apply_github_baseline "$SCION_LOCAL_PATH" "$STOCK_LOCAL_PATH"

# optional setup guidance (interactive)
if [ "$DRY_RUN" != "true" ]; then
  # Check if recommended .gitignore entries are missing
  gitignore_missing=false
  [ -f ".gitignore" ] || gitignore_missing=true
  if [ "$gitignore_missing" = "false" ]; then
    grep -Fxq ".vscode/*.bak.*" ".gitignore" || gitignore_missing=true
    grep -Fxq ".devcontainer/tmp/" ".gitignore" || gitignore_missing=true
    grep -Fxq ".devcontainer/var/" ".gitignore" || gitignore_missing=true
    grep -Fxq ".composer/" ".gitignore" || gitignore_missing=true
  fi
  
  if [ "$gitignore_missing" = "true" ]; then
    if [ "$NON_INTERACTIVE" = "true" ]; then
      info "Non-interactive mode: automatically appending recommended .gitignore entries."
      ensure_gitignore_line() { local line="$1"; [ -f ".gitignore" ] || touch ".gitignore"; grep -Fxq "$line" ".gitignore" || printf '%s\n' "$line" >> ".gitignore"; }
      ensure_gitignore_line ".vscode/*.bak.*"; ensure_gitignore_line ".devcontainer/tmp/"; ensure_gitignore_line ".devcontainer/var/"; ensure_gitignore_line ".composer/"
    else
      if prompt_confirm "Append recommended .gitignore entries for graft scion snapshots and temp dirs?" yes; then
        ensure_gitignore_line() { local line="$1"; [ -f ".gitignore" ] || touch ".gitignore"; grep -Fxq "$line" ".gitignore" || printf '%s\n' "$line" >> ".gitignore"; }
        ensure_gitignore_line ".vscode/*.bak.*"; ensure_gitignore_line ".devcontainer/tmp/"; ensure_gitignore_line ".devcontainer/var/"; ensure_gitignore_line ".composer/"
        info ".gitignore updated with recommended entries."
      fi
    fi
  fi
  
  # Check if README.md exists and lacks Codespaces badge
  if [ "$NON_INTERACTIVE" != "true" ] && [ -f "README.md" ]; then
    has_codespaces_badge=false
    grep -q "github.com/codespaces" "README.md" && has_codespaces_badge=true
    
    if [ "$has_codespaces_badge" = "false" ]; then
      debug "README exists but no Codespaces badge found"
      if prompt_confirm "Insert Codespaces badge & graft credit into README.md?" yes; then
        origin="$(git config --get remote.origin.url || true)"
        debug "origin: '$origin'"
        if [[ "$origin" =~ github.com ]]; then owner_repo="${origin#*github.com[:/]}"; owner_repo="${owner_repo%.git}"; else owner_repo="${GITHUB_REPOSITORY:-}"; fi
        debug "owner_repo: '$owner_repo'"
        branch="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo main)"
        debug "branch: '$branch'"
        badge_line="[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=${branch}&repo=${owner_repo})"
        credit_line='<img src=".devcontainer/assets/icon.svg" width="64" height="64" alt="cs-grafting" />Codespace created with [evlist/codespaces-grafting](https://github.com/evlist/codespaces-grafting) -'
        tmp="$(mktemp)"; { printf "%s\n%s\n\n" "$credit_line" "$badge_line" ; cat "README.md"; } > "$tmp"; mv "$tmp" "README.md"
        info "Inserted Codespaces badge and credit into README.md"
      fi
    fi
  fi
fi

# record provenance BEFORE commit
SCION_COMMIT=""
[ -d "$SCION_LOCAL_PATH/.git" ] && SCION_COMMIT="$(git -C "$SCION_LOCAL_PATH" rev-parse --short HEAD 2>/dev/null || true)"

SCION_REF_FOR_PROVENANCE="${SCION_REF:-}"
if [ -z "$SCION_REF_FOR_PROVENANCE" ]; then
  SCION_REF_FOR_PROVENANCE="$(detect_repo_ref "$SCION_LOCAL_PATH" 2>/dev/null || true)"
fi
record_provenance_file "$STOCK_LOCAL_PATH" "${SCION_CANON}" "${SCION_REF_FOR_PROVENANCE:-}" "${SCION_COMMIT:-unknown}"

SCION_DESC="${SCION_CANON}${SCION_REF_FOR_PROVENANCE:+@${SCION_REF_FOR_PROVENANCE}}"
if [ "$DRY_RUN" = "true" ]; then
  info "DRY RUN: skipping commit step"
else
  if commit_changes "$STOCK_LOCAL_PATH" "$SCION_DESC"; then
    info "Committed graft changes."
  else
    info "No commit created."
  fi
fi

# determine branch to push
if [ "$CREATE_BRANCH" = "true" ]; then BRANCH_TO_PUSH="$TARGET_STOCK_BRANCH"; else BRANCH_TO_PUSH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "HEAD")"; fi

# push decision (default yes interactive)
DO_PUSH=false
if [ "$PUSH_FLAG" = "true" ]; then
  DO_PUSH=true
else
  if [ "$NON_INTERACTIVE" = "true" ]; then
    DO_PUSH=false
  else
    if prompt_confirm "Push branch $BRANCH_TO_PUSH to origin now?" yes; then DO_PUSH=true; else DO_PUSH=false; fi
  fi
fi

if [ "$DO_PUSH" = "true" ]; then
  if [ "$DRY_RUN" = "true" ]; then
    info "DRY RUN: skipping push"
  else
    if push_branch "$STOCK_LOCAL_PATH" "$BRANCH_TO_PUSH"; then
      info "Push succeeded."
    else
      warn "Push failed."
      if [ "${GH_AVAILABLE}" = "true" ]; then
        info "Checking authenticated user and repository permissions..."
        AUTH_USER="$(gh api user --jq '.login' 2>/dev/null || true)"
        if [ -n "$AUTH_USER" ]; then
          info "Authenticated as: $AUTH_USER"
          perms="$(gh api -H "Accept: application/vnd.github+json" "/repos/${STOCK_CANON}" --jq '.permissions' 2>/dev/null || true)"
          info "Repository permissions (raw): $perms"
        fi
        if prompt_confirm "Run 'gh auth login --web' now to (re)authenticate?" no; then
          env -u GITHUB_TOKEN gh auth login --web || warn "gh auth login failed or cancelled."
          if push_branch "$STOCK_LOCAL_PATH" "$BRANCH_TO_PUSH"; then
            info "Push succeeded after gh auth."
          else
            warn "Push still failed after re-authentication."
          fi
        fi
      fi
    fi
  fi
else
  info "Skipping push as requested."
fi

info "Graft finished. Stock is at: $STOCK_LOCAL_PATH"
[ "$DRY_RUN" = "true" ] && info "Dry-run mode — no changes were pushed or recorded (except dry-run outputs)."

# expose a git() wrapper (export function) for spawned interactive shells (no repo files written)
git() { command git -c credential.helper='!gh auth git-credential' "$@"; }
export -f git

# If we earlier had an installation GITHUB_TOKEN and had to unset it to push,
# open a shell with GITHUB_TOKEN removed so git/gh use your user credentials.
AUTO_SHELL_NO_TOKEN=false
if [ "$INITIAL_GITHUB_TOKEN_PRESENT" = true ] && [ "$PUSH_SUCCEEDED_WITHOUT_TOKEN" = true ]; then AUTO_SHELL_NO_TOKEN=true; fi

if prompt_confirm "Open a new shell in $STOCK_LOCAL_PATH now?" no; then
  if [ "$AUTO_SHELL_NO_TOKEN" = true ]; then
    info "Opening a new shell in $STOCK_LOCAL_PATH with GITHUB_TOKEN removed (so git/gh will use your user credentials)..."
    cd "$STOCK_LOCAL_PATH"
    exec env -u GITHUB_TOKEN SHLVL=1 bash --login -i
  else
    info "Starting new shell at $STOCK_LOCAL_PATH..."
    cd "$STOCK_LOCAL_PATH"
    exec env SHLVL=1 bash --login -i
  fi
fi

exit 0