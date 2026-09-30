---
description: Push the branch and open a PR with a concise description and one label
argument-hint: "[issue-number]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Edit, Bash(git *), Bash(gh *)"
x-codex:
  interface:
    display_name: "Pull request"
    short_description: "Push the branch and open a PR from the template. Args: issue number"
---

# Create Pull Request

## Context

::target claude
!`git branch --show-current`
!`git log main..HEAD --oneline`
!`git diff main..HEAD --stat`
::end
::target codex
Run `git branch --show-current`, `git log main..HEAD --oneline`, and `git diff main..HEAD --stat` first. `$ARGUMENTS` below means the text passed after the skill name.
::end

## Instructions

1. **CHANGELOG check**: if the branch has user-facing changes and `CHANGELOG.md` is untouched, add the entry under `## Unreleased` and commit it before pushing.

2. **Local gate**: `composer test` must be green. Never push a red branch.

3. **Push**:
   ```bash
   git push -u origin HEAD
   ```

4. **Title**: `<type>(<scope>): <short description>`, under 70 characters. Take the type from the branch prefix (`feat/` → feat, `fix/` → fix, `ref/` → ref, `docs/` → docs). If `$ARGUMENTS` holds an issue number, start from its title:
   ```bash
   gh issue view <number> --json title -q '.title'
   ```

5. **Body**: read `.github/PULL_REQUEST_TEMPLATE.md` first and use its exact section headers, emojis included. Never hardcode them.
   - Say *what* changed and *why*, not how.
   - Add `Closes #<number>` so the merge closes the issue.
   - Keep it under 15 lines. No session links, tool footers, or attribution trailers.

6. **Create**:
   ```bash
   gh pr create --title "<title>" --assignee @me --label "<label>" --body "$(cat <<'EOF'
   <sections from .github/PULL_REQUEST_TEMPLATE.md>

   Closes #<issue-number>
   EOF
   )"
   ```
   Pick the one most relevant label:
   - `bug`: `fix/` branch
   - `enhancement`: `feat/` branch
   - `refactoring`: `ref/` branch, no behavior change
   - `documentation`: `docs/` branch
   - `pure testing`: only test changes
   - `dependencies`: dependency updates

7. **Report** the PR URL.
