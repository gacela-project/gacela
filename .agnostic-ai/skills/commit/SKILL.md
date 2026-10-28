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
Run `git diff --stat`, `git diff --cached --stat`, and `git status --short` first.
::end

## Instructions

1. **Auto-fix**:
   ```bash
   composer fix
   ```
   Review what the fixers changed. Rector can delete or privatize fixture methods that are dead on purpose; revert those and skip the file in `rector.php`.

2. **Stage** the changed files by name. Never `git add -A`.

3. **Changelog**: if the change needs an entry and has none, run `/changelog` and stage it.

4. **Message** in conventional commit format:
   - Use the argument as the message when given; otherwise derive it from the staged diff.
   - Follow the commit rules under "Git and pull requests" in `AGENTS.md`.

5. **Commit**:
   ```bash
   git commit -m "<message>"
   ```
   The pre-commit hook runs the gate, so do not run it again first. If the hook fails, fix the cause, restage, and commit again. Never pass `--no-verify`. Without the hook (it is installed by `composer install`), run the gate before committing.

6. **Report**: commit hash, message, and files included.
