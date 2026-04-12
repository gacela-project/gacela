---
description: Auto-fix, lint, test, and commit changes with a conventional commit message
argument-hint: "[optional commit message]"
disable-model-invocation: true
allowed-tools: "Read, Edit, Bash(composer *), Bash(./vendor/bin/*), Bash(git *)"
---

# Commit

## Context

!`git diff --stat`
!`git diff --cached --stat`
!`git status --short`

## Instructions

### Phase 1: Auto-fix

1. Run rector + cs-fixer on changed files:
   ```bash
   composer fix
   ```

2. If fixer modified files, review the changes and stage them.

### Phase 2: Quality gates

Run each step in order. Stop and fix issues before continuing.

3. **Static analysis**:
   ```bash
   composer quality
   ```

4. **Unit + integration + feature tests**:
   ```bash
   composer phpunit
   ```

If any step fails, fix the issue and re-run from that step. Do NOT proceed to commit with failures.

> Note: The pre-commit hook runs `composer quality` + `composer phpunit`. These gates catch issues early to avoid a slow hook failure.

### Phase 3: Commit

5. **Stage files** — add specific changed files by name (never `git add -A`).

6. **Draft commit message** using conventional commit format:
   - If `$ARGUMENTS` is provided, use it as the commit message
   - Otherwise, analyze the staged diff and generate one
   - Prefixes: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`
   - Add `(<scope>)` when changes are scoped to a single module
   - **NEVER mention AI/Claude in the message**

7. **Commit**:
   ```bash
   git commit -m "<message>"
   ```

8. **CHANGELOG check** — if the commit prefix is `feat:` or `fix:`, verify that `CHANGELOG.md` has been updated under `## Unreleased`. If not, warn the user before committing.

9. Report: commit hash, message, and files included.
