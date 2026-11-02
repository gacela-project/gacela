---
description: Use to work the queue of open Gacela issues that are unassigned or yours, several in a row.
argument-hint: "[--limit N] [--label foo] [--dry-run]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Bash(gh *), Bash(git *), Bash(composer *), Skill(gacela-gh-issue), Skill(pr)"
x-codex:
  interface:
    display_name: "GitHub issues"
    short_description: "Process every open issue that is unassigned or yours. Args: --limit, --label, --dry-run"
---

# GitHub Issues Watcher

## Purpose

Run `gacela-gh-issue` on every open issue that is unassigned or assigned to `@me`. Work sequentially unless told otherwise; follow explicit priority and parallelism instructions. One owner, branch and PR per issue; wait for dependencies to merge.

## Args

- `--limit N`: process at most N issues this run (default: all).
- `--label foo`: only issues carrying label `foo`.
- `--dry-run`: list the issues that would be processed; change nothing.

Strip a leading `#` from issue numbers.

## Phase 1: Discover

Search cannot OR these, so run two queries and merge:

```bash
# Unassigned
gh issue list --state open --search "no:assignee" \
  --json number,title,labels,assignees,createdAt --limit 200

# Assigned to me
gh issue list --state open --assignee "@me" \
  --json number,title,labels,assignees,createdAt --limit 200
```

Merge:
- Deduplicate by `number`.
- Keep issues whose `assignees` is empty or holds the current user (`gh api user -q .login`).
- Apply `--label`.
- Order as the user asked, bugs first. Otherwise by priority label, then oldest `createdAt`. Apply `--limit` after ordering.

Print one `#<num> <title> [unassigned|@me]` per line. Exit if empty.

## Phase 2: Worktree Sanity

Before touching any issue:

```bash
git status --porcelain
```

Refuse new work in a dirty worktree. When resuming an issue, continue its own changes. Never stash or reset another session's work; check the current branch, worktree and live processes, not the reflog.

For sequential work, sync `main`:

```bash
git checkout main && git pull --ff-only origin main
```

If `main` has diverged from `origin/main`, report it. Never reset `main`.

For authorized parallel work, give each issue its own worktree and a branch from fresh `origin/main`; never switch a checkout another worker owns. Respect the concurrency limit, run expensive checks centrally, never run typechecks concurrently.

## Phase 3: Process Loop

For each ready issue:

1. **Re-check assignment**:
   ```bash
   gh issue view <num> --json assignees -q '.assignees[].login'
   ```
   Empty or only you: proceed. Any other login: skip. Check current code and related PRs: it may already be fixed.

2. **Run the `gacela-gh-issue` skill** with the issue number. It owns assignment, branch, TDD, gate, changelog, commit and PR.

3. **Wait for CI**:
   ```bash
   gh pr checks --watch
   ```
   Required checks must be green on the current head before merging. Fix routine failures on the branch. After a timeout, inspect the live run before restarting it.

4. **Merge**:
   ```bash
   gh pr merge --auto --squash --admin
   ```
   `BLOCKED` or `REVIEW_REQUIRED` needs `--admin`. Never run or dispatch mutation testing from the queue.

5. **Sync `main`**:
   ```bash
   git checkout main && git pull --ff-only origin main
   ```

After a PR merges, update affected branches, resolve conflicts and rerun only the checks the update affects. Do not repeat a gate on unchanged code.

## Stop Conditions

Fix routine test, style and CI failures within the issue. If it cannot proceed, report the exact blocker and keep its worktree. Stop the run, unless the user asked to keep going: then continue only with independent ready issues. Never count a pending or failed check as green, retry blindly, or force a blocked dependency.

Stop at `--limit` or when no ready issue remains. Report each unresolved issue with its PR or branch.

## Dry Run

Run Phase 1 only and print the queue. Change nothing.

## Notes

- CI runs on Windows too: for a change that touches paths, check the Windows job before merging.
- One issue per PR unless the issue says otherwise.
