---
description: Create a new versioned Gacela release via release.sh (canonical automation)
argument-hint: "[version]"
disable-model-invocation: true
---

# Release

Canonical release automation lives in [`release.sh`](../../../release.sh). Always delegate to it — do not perform release steps manually. See [`.github/RELEASE.md`](../../../.github/RELEASE.md) for full reference.

## Context

!`git branch --show-current`
!`git status --porcelain`
!`git describe --tags --abbrev=0 2>/dev/null || echo "no tags"`

## Instructions

### Phase 1: Pre-flight

1. Abort if not on `main`. Must be clean tree (in-sync with `origin/main`).
2. Confirm `gh` CLI authenticated: `gh auth status`.
3. Confirm `## Unreleased` section in `CHANGELOG.md` has content:
   ```bash
   awk '/^## Unreleased/{flag=1;next} /^## /{flag=0} flag' CHANGELOG.md
   ```
   Abort if empty.
4. Determine version:
   - If `$ARGUMENTS` provides `X.Y.Z`, validate format.
   - Otherwise, suggest bump based on Unreleased content (breaking → major, feat → minor, fix only → patch).

### Phase 2: Dry-run preview

5. Show planned changes first:
   ```bash
   ./release.sh X.Y.Z --dry-run
   ```
   Confirm output with user before proceeding.

### Phase 3: Release

6. Execute:
   ```bash
   ./release.sh X.Y.Z
   ```
   Script handles: bump `bin/gacela`, rewrite `CHANGELOG.md`, run `composer quality && composer test`, commit `chore(release): X.Y.Z`, tag `X.Y.Z` (unprefixed), push `main` + tag, create GitHub release with notes from CHANGELOG section.

7. On failure mid-script, run:
   ```bash
   ./release.sh --rollback
   ```

### Phase 4: Verify

8. Confirm release:
   ```bash
   gh release view X.Y.Z
   ```
9. Report release URL to user.

## Rules

- **Tags unprefixed**: `1.14.2`, never `v1.14.2`.
- **Commit format**: `chore(release): X.Y.Z`.
- **Never** run manual `git tag` / `gh release create` when `release.sh` available.
- **Never** skip `composer quality && composer test` unless user explicitly passes `--skip-tests`.
- See `./release.sh --help` for all flags (`--dry-run`, `--force`, `--skip-tests`, `--without-gh-release`).
