#!/bin/bash
# PreToolUse hook for Codex: block patches to protected files. Claude Code asks through its permissions instead.
INPUT=$(cat)
FILES=$(echo "$INPUT" | jq -r '.tool_input.command // empty' | sed -n -E 's/^\*\*\* (Add|Update|Delete) File: (.*)$/\2/p; s/^\*\*\* Move to: (.*)$/\1/p')

while IFS= read -r FILE; do
    case "${FILE#./}" in
        .github/* | composer.lock)
            echo "$FILE is protected. Ask the user before changing it." >&2
            exit 2
            ;;
    esac
done <<< "$FILES"
exit 0
