#!/bin/bash
# PostToolUse hook: format the PHP files the agent edited. `agnostic-ai hook paths` reads both Claude and Codex payloads.
command -v agnostic-ai >/dev/null 2>&1 || exit 0
FILES=$(agnostic-ai hook paths) || exit 0

cd "${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null || pwd)}" 2>/dev/null
while IFS= read -r FILE; do
    if [[ "$FILE" == *.php && -f "$FILE" ]]; then
        ./vendor/bin/php-cs-fixer fix --quiet "$FILE" 2>/dev/null
    fi
done <<< "$FILES"
exit 0
