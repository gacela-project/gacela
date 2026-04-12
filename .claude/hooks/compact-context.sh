#!/bin/bash
# SessionStart hook: re-inject key context after compaction
cat <<'EOF'
## Context Reminder (post-compaction)

**Gacela** is a PHP modular framework (Facade/Factory/Provider/Config pattern).

- Conventional commits (`feat:`, `fix:`, `ref:`, `chore:`). NEVER mention AI/Claude.
- Test: `composer test` (all), `test-unit`, `test-integration`, `test-feature`
- Quality: `composer quality` (cs-fixer, psalm, phpstan)
- Auto-fix: `composer fix` (normalize + rector + cs-fixer). PHP edits auto-format via PostToolUse hook.
- Module pattern: Facade (public API), Factory (wiring), Provider (external deps), Config (values)
- Cross-module access ONLY through Facades, never direct instantiation.
- Protected files: `.github/*`, `composer.lock`
- PRs: follow `.github/PULL_REQUEST_TEMPLATE.md` exactly (with emoji prefixes).
- `feat:`/`fix:` commits must update `CHANGELOG.md` under `## Unreleased`.
EOF
