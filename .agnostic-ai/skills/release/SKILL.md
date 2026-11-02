---
description: Use to cut, publish or roll back a versioned Gacela release.
argument-hint: "[version]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Bash(git *), Bash(gh *), Bash(awk *), Bash(./release.sh *)"
x-codex:
  interface:
    display_name: "Release"
    short_description: "Cut a release with release.sh. Args: X.Y.Z"
---

# Release

Run `release.sh` for every release step; never do them by hand. Reference: `.github/RELEASE.md`.

## Context

::target claude
!`git branch --show-current`
!`git status --porcelain`
!`git describe --tags --abbrev=0 2>/dev/null || echo "no tags"`
::end
::target codex
Run `git branch --show-current`, `git status --porcelain`, and `git describe --tags --abbrev=0` first.
::end

## Instructions

### Phase 1: Pre-flight

1. Abort unless on `main` with a clean tree in sync with `origin/main`.
2. Confirm `gh auth status` succeeds.
3. Confirm `## Unreleased` in `CHANGELOG.md` has content:
   ```bash
   awk '/^## Unreleased/{flag=1;next} /^## /{flag=0} flag' CHANGELOG.md
   ```
   Abort if empty.
4. Pick the version:
   - If the argument is `X.Y.Z`, validate the format.
   - Otherwise suggest a bump from Unreleased: breaking → major, `### Added` → minor, fixes only → patch. With no version, `release.sh` bumps the minor.

### Phase 2: Dry run

5. Preview and confirm the output with the user:
   ```bash
   ./release.sh X.Y.Z --dry-run
   ```

### Phase 3: Release

6. Run:
   ```bash
   ./release.sh X.Y.Z
   ```
   The script rewrites `CHANGELOG.md`, checks GitHub check-runs are green for HEAD (no local tests), commits `chore(release): X.Y.Z`, creates a signed tag `X.Y.Z`, pushes `main` and the tag, and creates the GitHub release from the changelog section. No file holds the version; the tag does.

7. If it fails midway:
   ```bash
   ./release.sh --rollback
   ```
   Restores `CHANGELOG.md` from the latest backup.

### Phase 4: Verify

8. Check the release, the tag on Packagist, and `git archive X.Y.Z` for what ships.
   ```bash
   gh release view X.Y.Z
   ```
   Use `git archive`, not `composer archive`: `composer infection` can leave a mangled fixture directory such as `\\ar/...` (from `/var/...`) that breaks `composer archive`. Packagist builds from `git archive`.
9. Report the release URL.

## Rules

- Tags are unprefixed: `1.14.2`, never `v1.14.2`.
- Never run `git tag` or `gh release create` by hand.
- Use `--skip-tests` (skips the CI check) or `--force` (skips confirmation) only when asked. `--without-gh-release` tags and pushes without a GitHub release.
