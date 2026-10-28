#!/bin/bash
# SessionStart hook: re-inject key context after compaction, read from the generated AGENTS.md so the rules live in one place.
cd "${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null || pwd)}" 2>/dev/null || exit 0
[ -f AGENTS.md ] || exit 0

echo "## Context Reminder (post-compaction)"
awk '
    /^## / { keep = ($0 == "## Commands" || $0 == "## Git and pull requests"); if (keep) print ""; }
    keep { print }
' AGENTS.md
