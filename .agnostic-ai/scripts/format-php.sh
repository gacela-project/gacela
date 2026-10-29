#!/bin/bash
# PostToolUse hook: agnostic-ai reads edited paths from either target's payload.
FILES=$(agnostic-ai hook paths 2>/dev/null) || exit 0

cd "${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null || pwd)}" 2>/dev/null || exit 0
while IFS= read -r FILE; do
    if [[ "$FILE" == *.php && -f "$FILE" ]]; then
        ./vendor/bin/php-cs-fixer fix --quiet -- "$FILE" 2>/dev/null
    fi
done <<< "$FILES"
exit 0
