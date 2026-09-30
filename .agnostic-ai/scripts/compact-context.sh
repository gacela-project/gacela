#!/bin/bash
# SessionStart hook: re-inject key context after compaction
cat <<'EOF'
## Context Reminder (post-compaction)

**Gacela** is a PHP 8.3+ modular framework: Facade (public API), Factory (builds services), Provider (external deps), Config (values).

- Cross-module access ONLY through Facades, never direct instantiation.
- Conventional commits (`feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`). NEVER mention AI/Claude.
- `feat:`/`fix:` commits update `CHANGELOG.md` under `## Unreleased` in the same commit.
- Gate: `composer test` (quality + phpunit). One class: `./vendor/bin/phpunit --filter=ClassName`.
- Auto-fix: `composer fix`. PHP edits auto-format via the PostToolUse hook.
- Tests run in random order and on Windows: no shared state, paths via `DIRECTORY_SEPARATOR`.
- Protected files: `.github/*`, `composer.lock`.
- PRs: follow `.github/PULL_REQUEST_TEMPLATE.md` exactly (with emoji headers).
EOF
