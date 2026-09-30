---
description: Walk over all open GitHub issues that are unassigned or assigned to the current user, and process each one with the gh-issue skill, one after another.
argument-hint: "[--limit N] [--label foo] [--dry-run]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Bash(gh *), Bash(git *), Bash(composer *), Skill(gh-issue), Skill(pr)"
x-codex:
  interface:
    display_name: "GitHub issues"
    short_description: "Process every open issue that is unassigned or yours. Args: --limit, --label, --dry-run"
---

# GitHub Issues Watcher

## Purpose

Process every open GitHub issue that is **unassigned** or **assigned to the current user (`@me`)**, one after another, by handing each to the `gh-issue` skill. Stop on the first hard failure so it can be inspected.

## Args

- `--limit N`: process at most N issues this run (default: all).
- `--label foo`: only issues carrying label `foo`.
- `--dry-run`: list the issues that would be processed; change nothing.

Strip a leading `#` from issue numbers.

## Phase 1: Discover

Fetch open issues that are unassigned **or** assigned to `@me`, oldest first. GitHub search does not OR these cleanly, so run two queries and merge:

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
- Apply `--label`, then `--limit`.
- Sort ascending by `createdAt` (FIFO).

Print the queue, one `#<num> <title> [unassigned|@me]` per line. If it is empty, exit cleanly.

## Phase 2: Worktree Sanity

Before touching any issue:

```bash
git status --porcelain
```

Abort if the worktree is dirty. Never auto-stash. Another session may be writing to this repo, so also check `git reflog -5` for commits you did not make; abort if there are any. Then:

```bash
git fetch origin main
git checkout main && git reset --hard origin/main
```

## Phase 3: Process Loop

For each issue in the queue:

1. **Re-check assignment**, since someone may have taken it:
   ```bash
   gh issue view <num> --json assignees -q '.assignees[].login'
   ```
   Empty or only you: proceed. Any other login: skip the issue.

2. **Run the `gh-issue` skill** with the issue number. It owns self-assignment, the branch, the TDD implementation, `composer test`, the changelog entry, the `Related to #<num>` commit, and the PR.

3. **Refactor pass** over every file the issue touched. Remove duplication, dead branches, and unused parameters. Fix naming drift from the surrounding module. Follow `.agnostic-ai/rules/php.md`. Drop speculative abstractions. Re-run `composer test` and push the result as a separate `ref(<scope>): ...` commit with `Related to #<num>`. If nothing needs changing, say so in the PR body.

4. **Wait for CI**:
   ```bash
   gh pr checks --watch
   ```
   Every check must be green before merging; Scrutinizer is the only one allowed to fail. Fix red checks on the branch.

5. **Merge**:
   ```bash
   gh pr merge --auto --squash --admin
   ```
   A `BLOCKED` or `REVIEW_REQUIRED` state needs `--admin`. Full infection runs only on the push to `main`, so watch that run too.

6. **Sync `main`** for the next issue:
   ```bash
   git checkout main && git fetch origin main && git reset --hard origin/main
   ```

## Stop Conditions

Halt the loop and report the failure when:

- The `gh-issue` skill errors out or leaves the worktree dirty.
- `composer test` fails after implementation.
- CI stays red after one fix attempt.
- The merge is blocked beyond what `--admin` bypasses.
- `--limit` is reached, or the queue is empty.

Do not retry blindly. Report which issue failed and why.

## Dry Run

With `--dry-run`, run Phase 1 only and print the queue. No assignment, no branches, no commits.

## Preconditions

- `gh` is authenticated and can read issues and open and merge PRs.
- The worktree is clean, and `main` tracks `origin/main`.
- The `gh-issue` and `pr` skills are available.

## Notes

- The pre-commit hook (`tools/git-hooks/pre-commit.sh`) runs `composer quality` and `composer phpunit` on every commit. During implementation, run focused tests (`./vendor/bin/phpunit --filter=ClassName`) and let the hook run the full suite.
- PRs run CI on Windows too. When a change touches paths, check the Windows job before merging.
- Keep one issue per PR unless the issue asks otherwise.
- Never mention Claude, AI, or LLM in commits or PRs.
