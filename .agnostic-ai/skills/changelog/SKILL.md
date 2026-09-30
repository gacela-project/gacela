---
description: Update the CHANGELOG.md Unreleased section from recent commits or a manual entry
argument-hint: "[entry text]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Edit, Bash(git *)"
---

# Update Changelog

## Context

::target claude
!`git log $(git describe --tags --abbrev=0 2>/dev/null || echo HEAD~20)..HEAD --oneline`
::end
::target codex
Run `git log $(git describe --tags --abbrev=0 2>/dev/null || echo HEAD~20)..HEAD --oneline` first. `$ARGUMENTS` below means the text passed after the skill name.
::end

## Instructions

1. Read `CHANGELOG.md` and match the entries already under `## Unreleased`.

2. If `$ARGUMENTS` is provided, add it as an entry under the matching section.

3. If not, draft entries from the commits since the last tag. Skip what users never see: `chore:`, CI, tests, internal refactoring. Show the draft before writing.

4. Entry format:
   - Group under the headings the file already uses: `### Added`, `### Changed`, `### Fixed`, `### Removed`, `### Deprecated`, `### Performance`. Create the heading if the section lacks it.
   - One `- ` bullet per change. Describe the behavior as the user sees it now, and for a fix, what went wrong before: "`make:module` writes under the project root ... Run from a subdirectory, they wrote the module under the current directory".
   - Code in backticks: `` `ClassName::method()` ``, `` `make:module` ``.
   - End with the issue link when there is one: `([#912](https://github.com/gacela-project/gacela/issues/912))`.
   - Prefix breaking changes with **BREAKING**.

5. Edit `CHANGELOG.md`. A `feat:` or `fix:` change ships its entry in the same commit.
