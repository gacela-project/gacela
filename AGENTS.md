# Repository Guidelines

## Project Structure & Module Organization

Source code lives in `src/` under the `Gacela\` namespace, grouped by module-responsibility folders. Public CLI tooling
sits in `bin/` (notably `bin/gacela`), shared fixtures and sample data in `data/`, and documentation assets in `docs/`.
Tests mirror production namespaces inside `tests/` with `unit`, `integration`, and `feature` suites so cross-module
behaviour stays isolated.

## Build, Test, and Development Commands

- `composer install` — install PHP 8.1+ dependencies and trigger repo git hooks.
- `composer test` — run linting, static analysis, and the full PHPUnit matrix (`unit`, `integration`, `feature`).
- `composer quality` — quick guardrail: php-cs-fixer dry run, Psalm, then PHPStan.
- `composer csfix` / `composer csrun` — auto-fix or check formatting with php-cs-fixer config.
- `composer phpbench` — execute aggregate performance benchmarks.
- `composer infection` — mutation testing; requires Xdebug enabled.

## Coding Style & Naming Conventions

We follow PSR-12 with 4-space indentation, one statement per line, and trailing commas on multiline arrays. Classes and
interfaces use StudlyCase (`ModuleConfig.php`), services use explicit suffixes (`*Factory`, `*Facade`,
`*DependencyProvider`). Keep constructors typed, return types explicit, and favor `final` classes when extensibility is
not required. Run `composer csrun` before pushing; `phpstan.neon`, `psalm.xml`, and `rector.php` codify stricter rules
than vanilla PSR guidelines.

## Testing Guidelines

PHPUnit provides all suites; create tests beside the production namespace and suffix files with `Test.php`. Prefer
targeted unit tests that isolate module boundaries, and cover cross-module flows in `integration` or `feature`. Use
`composer test-unit`, `composer test-integration`, or `composer test-feature` for suite-specific runs. Mutation tests (
`composer infection`) and coverage reports (`composer test-coverage`) write artifacts to `data/coverage-*`; review
reports before merging substantial changes.

## Commit & Pull Request Guidelines

Commit messages follow short, imperative prefixes (`feat:`, `fix:`, `refactor:`, `docs:`, `deps:`). Keep subject ≤72
characters and add context in the body when behaviour changes. For pull requests: describe the problem, highlight
solution scope, list validation commands (at minimum `composer test`), and link issues or discussions. Include
screenshots when updating docs or CLI output. Ensure PRs stay scoped to a single concern to simplify review and release
notes generation.
