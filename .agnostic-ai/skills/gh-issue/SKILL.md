---
description: Take one GitHub issue end to end. Fetch it, branch, implement with TDD, and open a PR that closes it. Use when asked to work on, fix, or implement a specific issue by number or URL.
argument-hint: "[issue-number]"
x-codex:
  interface:
    display_name: "GitHub issue"
    short_description: "Take one issue end to end: branch, TDD, PR. Args: issue number"
---

# GitHub Issue Workflow

## Context

::target claude
!`gh issue view ${ARGUMENTS#\#} --json title,body,labels,assignees,state,comments 2>/dev/null || echo "Provide an issue number"`
::end
::target codex
Run `gh issue view <number> --json title,body,labels,assignees,state,comments` first, with the issue number passed after the skill name.
::end

## Phase 1: Setup

1. **Parse the issue number** (strip a leading `#`). Read the body and every comment: later comments often change the scope.

2. **Check the claims.** Verify on current `main` that the problem still exists before acting on the issue's findings. If it is already fixed, say so on the issue and stop.

3. **Assign yourself** if unassigned:
   ```bash
   gh issue edit <number> --add-assignee @me
   ```

4. **Branch** from fresh `main`. Prefix by label: `bug` → `fix/`, `enhancement` → `feat/`, `documentation` → `docs/`, `refactoring` → `ref/`, otherwise `feat/`. Name: `<prefix><number>-<slug>`.
   ```bash
   git checkout main && git pull
   git checkout -b <branch-name>
   ```

## Phase 2: Plan

5. Plan before coding:
   - The acceptance scenario the issue asks for, and what is out of scope.
   - Affected files, respecting module boundaries (Facades only across modules).
   - The TDD order: which failing test comes first.
   Stay in the issue's scope. File a follow-up issue for unrelated problems you find.

## Phase 3: Implement

6. TDD: failing test first, minimal code to pass, then refactor with tests green.

7. Update `docs/` pages that state the old behavior, flag lists, or exit codes.

8. Run the full gate and fix every failure:
   ```bash
   composer test
   ```

## Phase 4: Ship

9. Add the `## Unreleased` entry in `CHANGELOG.md` for user-facing changes, in the same commit.

10. Commit:
    ```bash
    git add <specific-files>
    git commit -m "<type>(<scope>): <description>

    Related to #<number>"
    ```

11. Open the PR with the `pr` skill and `#<number>`, so the body says `Closes #<number>`.

## Checklist

- [ ] Issue and every comment read; claims verified on `main`
- [ ] Self-assigned, branch from fresh `main`
- [ ] Tests written first
- [ ] Docs swept for the old behavior
- [ ] `composer test` passes
- [ ] Changelog updated in the same commit
- [ ] PR opened and closes the issue
