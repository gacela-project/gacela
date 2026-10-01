---
description: Create a new versioned Gacela release via release.sh
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

`release.sh` is the release automation. Always run it; never do release steps by hand. `.github/RELEASE.md` has the full reference.

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
   - Otherwise suggest a bump from the Unreleased content: breaking → major, `### Added` → minor, fixes only → patch. With no version, `release.sh` bumps the minor.

### Phase 2: Dry run

5. Preview, and confirm the output with the user before going on:
   ```bash
   ./release.sh X.Y.Z --dry-run
   ```

### Phase 3: Release

6. Run:
   ```bash
   ./release.sh X.Y.Z
   ```
   The script rewrites `CHANGELOG.md`, checks that CI is green for HEAD through GitHub check-runs (it does not run the tests locally), commits `chore(release): X.Y.Z`, creates a signed tag `X.Y.Z`, pushes `main` and the tag, and creates the GitHub release from the changelog section. The version comes from the git tag, so no file holds it.

7. If it fails midway:
   ```bash
   ./release.sh --rollback
   ```
   This restores `CHANGELOG.md` from the latest backup.

### Phase 4: Verify

8. Confirm the release, then check the published artifact: the tag on Packagist, and `git archive X.Y.Z` for what ships.
   ```bash
   gh release view X.Y.Z
   ```
9. Report the release URL.

## Rules

- Tags are unprefixed: `1.14.2`, never `v1.14.2`.
- Never run `git tag` or `gh release create` by hand.
- Use `--skip-tests` (skips the CI check) or `--force` (skips confirmation) only when the user asks. `--without-gh-release` tags and pushes without a GitHub release.
