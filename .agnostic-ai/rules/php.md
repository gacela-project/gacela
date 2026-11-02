---
description: PHP style and testing conventions for src/ and tests/.
globs:
  - src/**
  - tests/**
---

# PHP Conventions

## Code Style

- PSR-12 via php-cs-fixer and rector. A PostToolUse hook formats each edited PHP file when `agnostic-ai` or `jq` is on PATH.
- PHPStan strict rules, Psalm level 1 (`phpstan.neon`, `psalm.xml`, `rector.php`).
- Classes use StudlyCase. Pillars use their suffix: `*Facade`, `*Factory`, `*Provider`, `*Config`.
- `final` classes unless inheritance is needed. `readonly` properties where possible.
- Typed constructor parameters and explicit return types.
- Add a `use` import in the same edit as the code that uses it: the formatter hook strips unused imports.

## Testing

- PHPUnit 12. Files end in `Test.php`; methods are snake_case: `test_it_resolves_facade_from_factory()`.
- `tests/Unit/` mirrors `src/`. Cross-module flows go in `tests/Integration/`, end-to-end in `tests/Feature/`.
- Tests pass in any order: no reliance on another test's state; clean up only what the test created. On an intermittent failure, re-run with `--random-order-seed=<seed>` (PHPUnit prints it) before blaming the change.
- CI runs on Windows too: build expected paths with `DIRECTORY_SEPARATOR`.
- Never write `--` inside an XML comment in a fixture: Psalm then reports a misleading `Missing psalm node`.
- Test behavior, not characters. Do not chase mutation score on output or rendering code with golden-master tests.
