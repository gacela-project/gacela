---
description: Auto-fix, verify, and commit changes with a conventional commit message
argument-hint: "[optional commit message]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Edit, Bash(composer *), Bash(./vendor/bin/*), Bash(git *)"
x-codex:
  interface:
    display_name: "Commit"
    short_description: "Auto-fix, check the changelog, and commit with a conventional message"
---

# Commit

## Context

::target claude
!`git diff --stat`
!`git diff --cached --stat`
!`git status --short`
::end
::target codex
Run `git diff --stat`, `git diff --cached --stat`, and `git status --short` first. `$ARGUMENTS` below means the text passed after the skill name.
::end

## Instructions

1. **Auto-fix**:
   ```bash
   composer fix
   ```
   Review what the fixers changed. Rector can delete or privatize fixture methods that are dead on purpose; revert those and skip the file in `rector.php`.

2. **Stage** the changed files by name. Never `git add -A`.

3. **CHANGELOG check**: a `feat:` or `fix:` change must update `## Unreleased` in `CHANGELOG.md` in this commit. If it is missing, add it now.

4. **Message** in conventional commit format:
   - Use `$ARGUMENTS` when provided; otherwise derive it from the staged diff.
   - Prefixes: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`. Add `(<scope>)` for a single module.
   - Never mention Claude, AI, or LLM.

5. **Commit**:
   ```bash
   git commit -m "<message>"
   ```
   The pre-commit hook runs `composer quality` and `composer phpunit`, so do not run them again first. If the hook fails, fix the cause, restage, and commit again. Never pass `--no-verify`. Without the hook (it is installed by `composer install`), run `composer test` before committing.

6. **Report**: commit hash, message, and files included.
