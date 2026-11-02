---
description: Use to fix, implement or pick up one Gacela GitHub issue by number, through to a PR.
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

1. **Parse the issue number** (strip a leading `#`). Read the body and every comment; later ones change the scope.

2. **Check the claims** on current `main`. If already fixed, say so on the issue and stop.

3. **Assign yourself** if unassigned:
   ```bash
   gh issue edit <number> --add-assignee @me
   ```

4. **Branch** from fresh `main`. Prefix by label: `bug` → `fix/`, `enhancement` → `feat/`, `documentation` → `docs/`, `refactoring` → `ref/`, otherwise `feat/`. Name: `<prefix><number>-<slug>`.
   ```bash
   git checkout main && git pull --ff-only
   git checkout -b <branch-name>
   ```

## Phase 2: Plan

5. Plan before coding: the acceptance scenario and non-goals; affected files (Facades only across modules); the first failing test. Stay in scope; file a follow-up issue for unrelated problems.

## Phase 3: Implement

6. TDD: failing test first, minimal code to pass, then refactor with tests green.

7. Update `docs/` pages that state the old behavior, flags or exit codes.

8. Run the gate; fix every failure.

## Phase 4: Ship

9. For a user-facing change, run `/changelog` and commit its entry with the change.

10. Commit:
    ```bash
    git add <specific-files>
    git commit -m "<type>(<scope>): <description>

    Related to #<number>"
    ```

11. Open the PR with the `pr` skill and `#<number>` (body says `Closes #<number>`).

## Checklist

- [ ] Issue and every comment read; claims verified on `main`
- [ ] Self-assigned, branch from fresh `main`
- [ ] Tests written first
- [ ] Docs swept for the old behavior
- [ ] The gate passes
- [ ] Changelog entry in the same commit
- [ ] PR opened and closes the issue
