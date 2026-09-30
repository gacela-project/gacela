#!/bin/bash
# PostToolUse hook: auto-format PHP files after the agent edits them.
# Claude Code sends tool_input.file_path; Codex sends an apply_patch body in tool_input.command.
INPUT=$(cat)
if [[ "$AGNOSTIC_AI_TARGET" == "codex" ]]; then
    FILES=$(echo "$INPUT" | jq -r '.tool_input.command // empty' | sed -n -E 's/^\*\*\* (Add|Update) File: (.*)$/\2/p; s/^\*\*\* Move to: (.*)$/\1/p')
else
    FILES=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')
fi

cd "${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null || pwd)}" 2>/dev/null
while IFS= read -r FILE; do
    if [[ "$FILE" == *.php && -f "$FILE" ]]; then
        ./vendor/bin/php-cs-fixer fix --quiet "$FILE" 2>/dev/null
    fi
done <<< "$FILES"
exit 0
