---
description: Run tests with smart filtering by scope, class, or file path
argument-hint: "[scope-or-filter]"
disable-model-invocation: true
allowed-tools: "Bash(composer *), Bash(./vendor/bin/phpunit *)"
---

# Quick Test Runner

1. If `$ARGUMENTS` is empty or `all`:
   ```bash
   composer test
   ```

2. If `$ARGUMENTS` is a known scope:
   - `quality` → `composer quality`
   - `unit` → `composer test-unit`
   - `integration` → `composer test-integration`
   - `feature` → `composer test-feature`
   - `quick` → `composer phpunit` (skip static analysis)
   - `bench` → `composer phpbench`
   - `infection` → `composer infection`

3. If `$ARGUMENTS` looks like a test class or method name:
   ```bash
   ./vendor/bin/phpunit --filter "$ARGUMENTS"
   ```

4. If `$ARGUMENTS` looks like a file path:
   ```bash
   ./vendor/bin/phpunit "$ARGUMENTS"
   ```

5. Report results clearly with pass/fail count.
