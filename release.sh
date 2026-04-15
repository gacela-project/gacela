#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

declare -r EXIT_SUCCESS=0
declare -r EXIT_VALIDATION_ERROR=1
declare -r EXIT_EXECUTION_ERROR=2

GITHUB_REPO_PATH="gacela-project/gacela"
GITHUB_REPO_URL="https://github.com/${GITHUB_REPO_PATH}"
RELEASE_FILES=("bin/gacela" "CHANGELOG.md")

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DRY_RUN=false
FORCE_MODE=false
SKIP_TESTS=false
WITH_GH_RELEASE=true

BACKUP_DIR=""
VERSION=""
CURRENT_VERSION=""
RELEASE_NOTES_FILE=""

log_info()    { echo -e "${BLUE}[INFO]${NC} $1" >&2; }
log_success() { echo -e "${GREEN}[OK]${NC} $1" >&2; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1" >&2; }
log_error()   { echo -e "${RED}[ERROR]${NC} $1" >&2; }
log_dry()     { echo -e "${YELLOW}[DRY-RUN]${NC} $1" >&2; }

show_usage() {
  cat >&2 <<EOF
Usage: ./release.sh [version] [options]

Arguments:
  version            Semantic version X.Y.Z (e.g., 1.13.0).
                     Omitted → auto-bump minor from current version.

Options:
  --dry-run          Preview changes without modifying files or pushing.
  --force            Skip interactive confirmation (for CI).
  --skip-tests       Skip 'composer quality' and 'composer test'.
  --without-gh-release
                     Tag and push, but do not create a GitHub release.
  --rollback         Restore bin/gacela and CHANGELOG.md from latest backup.
  -h, --help         Show this message.

Examples:
  ./release.sh                      # Auto-bump minor, interactive
  ./release.sh 1.13.0               # Explicit version
  ./release.sh 1.13.0 --dry-run     # Preview only
  ./release.sh --force --skip-tests # CI mode
  ./release.sh --rollback           # Undo local file changes
EOF
}

confirm() {
  [[ "$FORCE_MODE" == true ]] && return 0
  local prompt=$1
  echo -en "${YELLOW}${prompt} [y/N]: ${NC}" >&2
  read -r response
  [[ "$response" =~ ^[Yy]$ ]]
}

#########################
### PRE-FLIGHT CHECKS ###
#########################

preflight_gh_installed() {
  command -v gh >/dev/null 2>&1 || {
    log_error "gh CLI not installed. Install from https://cli.github.com/"
    return 1
  }
}

preflight_gh_auth() {
  gh auth status >/dev/null 2>&1 || {
    log_error "Not authenticated with GitHub. Run: gh auth login"
    return 1
  }
}

preflight_git_clean() {
  [[ -z "$(git status --porcelain)" ]] || {
    log_error "Working tree is dirty. Commit or stash changes first."
    return 1
  }
}

preflight_on_main() {
  local branch
  branch=$(git branch --show-current)
  [[ "$branch" == "main" ]] || {
    log_error "Not on main branch (on: $branch). Run: git checkout main"
    return 1
  }
}

preflight_up_to_date() {
  git fetch --quiet origin main
  local local_sha remote_sha
  local_sha=$(git rev-parse HEAD)
  remote_sha=$(git rev-parse origin/main)
  [[ "$local_sha" == "$remote_sha" ]] || {
    log_error "Local main is not in sync with origin/main. Pull or push first."
    return 1
  }
}

preflight_required_files() {
  for f in "${RELEASE_FILES[@]}"; do
    [[ -f "$f" ]] || { log_error "Missing required file: $f"; return 1; }
  done
}

preflight_changelog_has_unreleased() {
  grep -q "^## Unreleased$" CHANGELOG.md || {
    log_error "CHANGELOG.md missing '## Unreleased' section."
    return 1
  }
  local content
  content=$(awk '/^## Unreleased$/{flag=1;next} /^## \[/{flag=0} flag' CHANGELOG.md \
            | grep -v '^$' | head -1)
  [[ -n "$content" ]] || {
    log_error "CHANGELOG.md '## Unreleased' section is empty. Add release notes first."
    return 1
  }
}

run_preflight() {
  local ok=true
  log_info "Running pre-flight checks..."
  for check in preflight_gh_installed preflight_gh_auth \
               preflight_git_clean preflight_on_main preflight_up_to_date \
               preflight_required_files preflight_changelog_has_unreleased; do
    "$check" || ok=false
  done
  [[ "$ok" == true ]] || { log_error "Pre-flight checks failed."; exit $EXIT_VALIDATION_ERROR; }
  log_success "Pre-flight checks passed."
}

#########################
###  VERSION HANDLING ###
#########################

read_current_version() {
  CURRENT_VERSION=$(grep -oE "version:\s*'[0-9]+\.[0-9]+\.[0-9]+'" bin/gacela \
                    | head -1 | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")
  [[ -n "$CURRENT_VERSION" ]] || {
    log_error "Could not read current version from bin/gacela."
    exit $EXIT_VALIDATION_ERROR
  }
}

bump_minor() {
  local major minor
  major=$(echo "$CURRENT_VERSION" | cut -d. -f1)
  minor=$(echo "$CURRENT_VERSION" | cut -d. -f2)
  echo "${major}.$((minor + 1)).0"
}

validate_version() {
  [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
    log_error "Invalid version '$1'. Expected X.Y.Z."
    exit $EXIT_VALIDATION_ERROR
  }
  if git rev-parse "refs/tags/$1" >/dev/null 2>&1; then
    log_error "Tag $1 already exists."
    exit $EXIT_VALIDATION_ERROR
  fi
}

#########################
###  BACKUP & ROLLBACK###
#########################

backup_init() {
  BACKUP_DIR=".release-state/backup-$(date +%Y%m%d-%H%M%S)"
  mkdir -p "$BACKUP_DIR"
  for f in "${RELEASE_FILES[@]}"; do cp "$f" "$BACKUP_DIR/$(basename "$f")"; done
}

rollback_manual() {
  local latest
  latest=$(find .release-state -maxdepth 1 -type d -name 'backup-*' 2>/dev/null | sort -r | head -1)
  [[ -n "$latest" ]] || { log_error "No backup found in .release-state."; exit $EXIT_VALIDATION_ERROR; }
  log_info "Restoring from $latest"
  cp "$latest/gacela" bin/gacela
  cp "$latest/CHANGELOG.md" CHANGELOG.md
  log_success "Files restored. Remember to review 'git status'."
}

#########################
###   FILE UPDATES    ###
#########################

update_bin_gacela() {
  if [[ "$DRY_RUN" == true ]]; then
    log_dry "Would update bin/gacela: $CURRENT_VERSION → $VERSION"
    return
  fi
  sed -i.bak "s|version: '${CURRENT_VERSION}'|version: '${VERSION}'|" bin/gacela
  rm -f bin/gacela.bak
  log_success "bin/gacela → $VERSION"
}

capture_release_notes() {
  local notes
  notes=$(awk '
    /^## Unreleased$/ {flag=1; next}
    /^## \[/         {flag=0}
    flag             {lines[++n]=$0}
    END {
      start=1; while (start<=n && lines[start] ~ /^[[:space:]]*$/) start++
      end=n;   while (end>=start && lines[end] ~ /^[[:space:]]*$/) end--
      for (i=start; i<=end; i++) print lines[i]
    }
  ' CHANGELOG.md)

  [[ -n "$notes" ]] || {
    log_error "Captured release notes from '## Unreleased' are empty."
    exit $EXIT_VALIDATION_ERROR
  }

  if [[ "$DRY_RUN" == true ]]; then
    log_dry "Release notes (from CHANGELOG.md '## Unreleased'):"
    printf '%s\n' "$notes" | sed 's/^/    /' >&2
    RELEASE_NOTES_FILE="(dry-run)"
    return
  fi

  RELEASE_NOTES_FILE=".release-state/release-notes-${VERSION}.md"
  mkdir -p .release-state
  printf '%s\n' "$notes" > "$RELEASE_NOTES_FILE"
  log_success "Release notes captured → $RELEASE_NOTES_FILE"
}

update_changelog() {
  local date today header
  today=$(date +%Y-%m-%d)
  header="## [${VERSION}](${GITHUB_REPO_URL}/compare/${CURRENT_VERSION}...${VERSION}) - ${today}"

  if [[ "$DRY_RUN" == true ]]; then
    log_dry "Would rewrite '## Unreleased' → '$header' and insert fresh '## Unreleased'"
    return
  fi

  awk -v header="$header" '
    /^## Unreleased$/ && !done {
      print "## Unreleased"
      print ""
      print header
      done = 1
      next
    }
    { print }
  ' CHANGELOG.md > CHANGELOG.md.tmp && mv CHANGELOG.md.tmp CHANGELOG.md
  log_success "CHANGELOG.md updated with $VERSION"
}

#########################
###     TEST SUITE    ###
#########################

run_tests() {
  [[ "$SKIP_TESTS" == true ]] && { log_warn "Skipping tests (--skip-tests)"; return; }
  if [[ "$DRY_RUN" == true ]]; then
    log_dry "Would run: composer quality && composer test"
    return
  fi
  log_info "Running composer quality..."
  composer quality
  log_info "Running composer test..."
  composer test
  log_success "Quality + tests passed."
}

#########################
###    GIT & GH       ###
#########################

git_commit_and_tag() {
  if [[ "$DRY_RUN" == true ]]; then
    log_dry "Would: git add ${RELEASE_FILES[*]}; commit 'chore(release): $VERSION'; tag $VERSION"
    return
  fi
  git add "${RELEASE_FILES[@]}"
  git commit -m "chore(release): ${VERSION}"
  git tag -a "$VERSION" -m "Release $VERSION"
  log_success "Commit + tag $VERSION created."
}

git_push() {
  if [[ "$DRY_RUN" == true ]]; then
    log_dry "Would: git push origin main && git push origin $VERSION"
    return
  fi
  git push origin main
  git push origin "$VERSION"
  log_success "Pushed main + tag."
}

create_gh_release() {
  [[ "$WITH_GH_RELEASE" == true ]] || { log_warn "Skipping gh release (--without-gh-release)"; return; }
  if [[ "$DRY_RUN" == true ]]; then
    log_dry "Would: gh release create $VERSION --title $VERSION --notes-file $RELEASE_NOTES_FILE"
    return
  fi
  gh release create "$VERSION" \
    --title "$VERSION" \
    --notes-file "$RELEASE_NOTES_FILE"
  log_success "GitHub release $VERSION created."
}

#########################
###        MAIN       ###
#########################

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --dry-run)             DRY_RUN=true ;;
      --force)               FORCE_MODE=true ;;
      --skip-tests)          SKIP_TESTS=true ;;
      --without-gh-release)  WITH_GH_RELEASE=false ;;
      --rollback)            rollback_manual; exit $EXIT_SUCCESS ;;
      -h|--help)             show_usage; exit $EXIT_SUCCESS ;;
      -*)                    log_error "Unknown option: $1"; show_usage; exit $EXIT_VALIDATION_ERROR ;;
      *)                     VERSION="$1" ;;
    esac
    shift
  done
}

main() {
  parse_args "$@"

  run_preflight
  read_current_version

  [[ -n "$VERSION" ]] || VERSION=$(bump_minor)
  validate_version "$VERSION"

  log_info "Current: $CURRENT_VERSION  →  New: $VERSION"
  confirm "Proceed with release?" || { log_info "Aborted."; exit $EXIT_SUCCESS; }

  [[ "$DRY_RUN" == true ]] || backup_init

  capture_release_notes
  update_bin_gacela
  update_changelog
  run_tests
  git_commit_and_tag
  git_push
  create_gh_release

  log_success "Release $VERSION complete."
  [[ "$DRY_RUN" == true ]] && log_warn "This was a DRY-RUN. No files or remote state changed."
}

main "$@"
