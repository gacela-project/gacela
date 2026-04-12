---
description: Create a new versioned release with changelog and GitHub release
argument-hint: "[version]"
disable-model-invocation: true
---

# Release

## Context

!`git branch --show-current`
!`git status --porcelain`
!`git describe --tags --abbrev=0 2>/dev/null || echo "no tags"`

## Instructions

### Phase 1: Pre-flight Checks

1. Abort if not on `main` or if there are uncommitted changes (see context above).

2. **Check CHANGELOG.md has Unreleased content**:
   ```bash
   git diff $(git describe --tags --abbrev=0)..HEAD -- CHANGELOG.md
   ```
   Warn if `## Unreleased` section is empty.

3. **Determine next version**:
   - If `$ARGUMENTS` provides a version, validate format (X.Y.Z)
   - Otherwise, suggest based on changes (major for breaking, minor for features, patch for fixes)

### Phase 2: Release

4. **Update CHANGELOG.md** — rename `## Unreleased` to `## [X.Y.Z](https://github.com/gacela-project/gacela/compare/PREV...X.Y.Z) - YYYY-MM-DD`, add new empty `## Unreleased` section above it.

5. **Commit and tag**:
   ```bash
   git add CHANGELOG.md
   git commit -m "chore: release vX.Y.Z"
   git tag vX.Y.Z
   ```

6. **Push**:
   ```bash
   git push origin main --tags
   ```

7. **Create GitHub release**:
   ```bash
   gh release create vX.Y.Z --title "vX.Y.Z" --notes-from-tag
   ```

### Phase 3: Verify

8. **Confirm release was created**:
   ```bash
   gh release view vX.Y.Z
   ```

9. **Report the release URL** to the user.
