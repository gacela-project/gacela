---
description: Run tests with smart filtering by scope, class, or file path
argument-hint: "[scope-or-filter]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Bash(composer *), Bash(./vendor/bin/phpunit *)"
x-codex:
  interface:
    display_name: "Test"
    short_description: "Run tests by scope, class, or path. Args: unit, integration, feature, quick, or a filter"
---

# Quick Test Runner

1. If the argument is empty or `all`:
   ```bash
   composer test
   ```

2. If the argument is a known scope:
   - `quality` → `composer quality`
   - `unit` → `composer test-unit`
   - `integration` → `composer test-integration`
   - `feature` → `composer test-feature`
   - `quick` → `composer phpunit` (skip static analysis)
   - `bench` → `composer phpbench`
   - `infection` → `composer infection`

3. If the argument looks like a test class or method name:
   ```bash
   ./vendor/bin/phpunit --filter "<argument>"
   ```

4. If the argument looks like a file path:
   ```bash
   ./vendor/bin/phpunit "<argument>"
   ```

5. Report the pass and fail counts. The suite runs in random order: when a test fails only sometimes, re-run with `--random-order-seed=<seed>` (PHPUnit prints the seed) before blaming the change.
