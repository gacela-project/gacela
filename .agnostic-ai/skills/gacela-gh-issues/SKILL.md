---
description: Work ready Gacela issues that are unassigned or yours through gacela-gh-issue.
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

Process every ready GitHub issue that is **unassigned** or **assigned to the current user (`@me`)** through `gacela-gh-issue`. Work sequentially by default; honor explicit priority, parallelism and continuation instructions. Keep one owner, branch and PR per issue, and wait for dependencies to merge.

## Args

- `--limit N`: process at most N issues this run (default: all).
- `--label foo`: only issues carrying label `foo`.
- `--dry-run`: list the issues that would be processed; change nothing.

Strip a leading `#` from issue numbers.

## Phase 1: Discover

Fetch open issues that are unassigned **or** assigned to `@me`. GitHub search does not OR these cleanly, so run two queries and merge:

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
- Keep only issues whose `assignees` is empty or holds the current user (`gh api user -q .login`). Drop anyone else's.
- Apply `--label`.
- Follow the user's requested order and bugs-first policy. Otherwise sort by priority label, then oldest `createdAt`. Apply `--limit` after ordering.

Print the queue, one `#<num> <title> [unassigned|@me]` per line. If it is empty, exit cleanly.

## Phase 2: Worktree Sanity

Before touching any issue:

```bash
git status --porcelain
```

Refuse new work in a dirty worktree. When resuming an issue, inspect and continue its owned changes instead of treating them as unrelated dirt. Never auto-stash or reset another session's work. Historical reflog entries do not prove concurrent editing; inspect the current branch, worktree and live processes.

For sequential work, sync `main`:

```bash
git checkout main && git pull --ff-only origin main
```

If the pull refuses because `main` has diverged from `origin/main`, report it and preserve it. Never reset `main`.

For authorized parallel work, give each independent issue an isolated worktree and branch from fresh `origin/main`; do not switch a checkout another worker owns. Respect the requested concurrency limit and coordinate expensive checks centrally. Never run typechecks concurrently.

## Phase 3: Process Loop

For each ready issue, within the authorized concurrency limit:

1. **Re-check assignment**, since someone may have taken it:
   ```bash
   gh issue view <num> --json assignees -q '.assignees[].login'
   ```
   Empty or only you: proceed. Any other login: skip the issue. Recheck current code and related PRs before starting, since another worker may already have fixed it.

2. **Run the `gacela-gh-issue` skill** with the issue number. It owns self-assignment, the branch, the TDD implementation, the gate, the changelog entry, the `Related to #<num>` commit, and the PR.

3. **Wait for CI**:
   ```bash
   gh pr checks --watch
   ```
   Required checks must be green on the current head before merging. Fix routine failures on the branch. On continuation or an observation timeout, inspect the live run or process before restarting it; a missing tool handle is not proof it stopped.

4. **Merge**:
   ```bash
   gh pr merge --auto --squash --admin
   ```
   A `BLOCKED` or `REVIEW_REQUIRED` state needs `--admin`. Mutation testing is manual-only. Do not run or dispatch it automatically while processing the queue.

5. **Sync `main`** in its owned checkout for the next issue:
   ```bash
   git checkout main && git pull --ff-only origin main
   ```

After another PR merges, update affected branches, resolve conflicts and repeat checks affected by the update. Reuse successful validation on unchanged code instead of running the same gate in the worker, main thread and commit hook.

## Stop Conditions

Repair routine test, style and CI failures within the issue. If the issue cannot proceed, report the exact blocker and preserve its worktree. Stop the run by default; when the user explicitly asked to keep going, continue only with independent ready issues. Never count a pending or failed check as green, retry blindly, or force a blocked dependency.

Stop starting issues when `--limit` is reached or no ready issue remains. Report each unresolved issue and its PR or branch.

## Dry Run

With `--dry-run`, run Phase 1 only and print the queue. No assignment, no branches, no commits.

## Notes

- PRs run CI on Windows too. When a change touches paths, check the Windows job before merging.
- Keep one issue per PR unless the issue asks otherwise.
