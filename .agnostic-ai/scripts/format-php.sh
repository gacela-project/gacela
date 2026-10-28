#!/bin/bash
# PostToolUse hook: format the PHP files the agent edited. `agnostic-ai hook paths` reads both Claude and Codex payloads; jq reads the Claude one.
if command -v agnostic-ai >/dev/null 2>&1; then
    FILES=$(agnostic-ai hook paths) || exit 0
elif command -v jq >/dev/null 2>&1; then
    FILES=$(jq -r '.tool_input.file_path // empty') || exit 0
else
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null || pwd)}" 2>/dev/null
while IFS= read -r FILE; do
    if [[ "$FILE" == *.php && -f "$FILE" ]]; then
        ./vendor/bin/php-cs-fixer fix --quiet "$FILE" 2>/dev/null
    fi
done <<< "$FILES"
exit 0
