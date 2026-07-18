---
description: Walk over all open GitHub issues that are unassigned or assigned to the current user, and process each one via the /gh-issue skill, sequentially.
argument-hint: "[--limit N] [--label foo] [--dry-run]"
disable-model-invocation: true
allowed-tools: "Read, Bash(gh *), Bash(git *), Bash(composer *), Skill(gh-issue), Skill(pr)"
---

# GitHub Issues Watcher

## Purpose

Process every open GitHub issue that is **unassigned** or **assigned to the current user (`@me`)**, one after another, by delegating each to the `/gh-issue` skill. Stop on first hard failure so it can be inspected.

## Args

- `--limit N` — process at most N issues this run (default: all).
- `--label foo` — only issues carrying label `foo`.
- `--dry-run` — list issues that would be processed; do not invoke `/gh-issue`.

Strip leading `#` if user passes `#123` style.

## Phase 1: Discover

Fetch open issues that are unassigned **or** assigned to `@me`, oldest first. GitHub search does not OR these cleanly, so run two queries and merge:

```bash
# Unassigned
gh issue list \
  --state open \
  --search "no:assignee" \
  --json number,title,labels,assignees,createdAt \
  --limit 200

# Assigned to me
gh issue list \
  --state open \
  --assignee "@me" \
  --json number,title,labels,assignees,createdAt \
  --limit 200
```

Merge:
- Deduplicate by `number`.
- Keep only issues whose `assignees` array is empty **or** contains the current user (`gh api user -q .login`).
- Drop issues assigned to anyone else (defensive).
- Apply `--label` filter if given.
- Apply `--limit` if given.
- Sort ascending by `createdAt` (FIFO).

Print the queue: `#<num> <title> [assignee]` per line, where `[assignee]` is `unassigned` or `@me`. If empty, exit cleanly.

## Phase 2: Worktree Sanity

Before touching any issue:

```bash
git status --porcelain
git fetch origin main
git checkout main && git reset --hard origin/main
```

Abort if worktree dirty. Never auto-stash.

## Phase 3: Process Loop

For each issue in the queue:

1. Re-check assignment state (someone else may have grabbed it):
   ```bash
   gh issue view <num> --json assignees -q '.assignees[].login'
   me=$(gh api user -q .login)
   ```
   - Empty output → unassigned, proceed.
   - Only `$me` listed → already mine, proceed (skip self-assign step).
   - Any other login present → skip this issue.

2. Invoke the `/gh-issue` skill with the issue number. That skill owns:
   - self-assign via `gh issue edit <num> --add-assignee @me` (no-op if already assigned)
   - branch from fresh `main`, prefix from labels: `bug` → `fix/`, `enhancement` → `feat/`, `documentation` → `docs/`, `refactoring` → `ref/`, else `feat/`
   - TDD implementation (unit → integration → feature per module architecture)
   - `composer test` green locally (quality + phpunit)
   - changelog entry under `## Unreleased`
   - commit with `Related to #<num>` (conventional: `feat:`, `fix:`, `ref:`, `docs:`, `chore:`, `test:`)
   - PR opened via `/pr #<num>`

3. Before opening the PR, do a **refactor pass** over every file the issue touched — dedupe new code, drop dead branches/unused params, fix naming drift vs. surrounding module conventions, honor `.claude/rules/php.md`, remove speculative abstractions. Apply fixes, re-run `composer test`, and commit them as a separate `ref(...)` commit with `Related to #<num>`. If nothing needs changing, note that in the PR body instead of skipping silently.

4. After `/gh-issue` returns, wait for CI green on the PR:
   ```bash
   gh pr checks --watch
   ```
   Fix red checks on the branch before moving on.

5. Merge when allowed:
   ```bash
   gh pr merge --auto --squash --admin
   ```

6. Sync `main` for next iteration:
   ```bash
   git checkout main && git fetch origin main && git reset --hard origin/main
   ```

7. Continue with next issue.

## Stop Conditions

Halt the loop and surface the failure when:

- `/gh-issue` errors out or leaves the worktree dirty.
- `composer test` fails after implementation.
- CI stays red after one fix attempt.
- Merge is blocked by branch protection beyond `--admin` bypass.
- `--limit` reached.
- Queue empty.

Do **not** retry blindly. Report which issue failed and why.

## Dry Run

With `--dry-run`, only execute Phase 1 and print the queue. No assignment, no branching, no commits.

## Preconditions

- `gh` authenticated, can read issues, open and merge PRs.
- Worktree clean.
- `main` exists and tracks `origin/main`.
- `/gh-issue` and `/pr` skills available in this session.

## Notes

- The pre-commit hook (`tools/git-hooks/pre-commit.sh`) runs `composer quality` + `composer phpunit` on every commit — it is the local gate. During implementation run focused tests (`./vendor/bin/phpunit --filter=ClassName`); let the hook run the full suite at commit time.
- Treat GitHub CI as the full quality gate.
- Never split bundled changes into multiple PRs unless the issue explicitly demands it.
- Never mention Claude/AI/LLM in commits or PRs.
