---
description: Auto-fix code style with composer fix, then check what static analysis and tests still report
argument-hint: "[file-path]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Edit, Bash(composer *), Bash(./vendor/bin/*)"
x-codex:
  interface:
    display_name: "Fix"
    short_description: "Run composer fix, then report what static analysis still finds"
---

# Fix Code Quality Issues

## Instructions

1. Auto-fix. For the whole project (normalize, rector, cs-fixer):
   ```bash
   composer fix
   ```
   For one file:
   ```bash
   ./vendor/bin/php-cs-fixer fix "<argument>"
   ```
   Rector can delete or privatize fixture methods that are dead on purpose. Revert those and skip the file in `rector.php`.

2. Check what the fixers cannot fix:
   ```bash
   composer quality
   ```
   Fix the remaining Psalm, PHPStan, and module-cycle findings by hand.

3. Verify nothing broke:
   ```bash
   composer phpunit
   ```

4. Summarize what was fixed and what remains.
