---
description: PHP code style, quality rules, and testing conventions
globs:
  - src/**
  - tests/**
---

# PHP Conventions

## Code Style

- PSR-12, enforced by php-cs-fixer and rector. Edited PHP files are formatted by a PostToolUse hook, so no manual run is needed.
- PHPStan strict rules, Psalm level 1. `phpstan.neon`, `psalm.xml`, and `rector.php` are stricter than plain PSR.
- Classes use StudlyCase. Pillars use their suffix: `*Facade`, `*Factory`, `*Provider`, `*Config`.
- `final` classes unless inheritance is needed. `readonly` properties where possible.
- Typed constructor parameters and explicit return types everywhere.
- Add a `use` import in the same edit as the code that uses it: the formatter hook strips unused imports.

## Testing

- PHPUnit 12. Files end in `Test.php`; methods are snake_case: `test_it_resolves_facade_from_factory()`.
- `tests/Unit/` mirrors `src/`. Cross-module flows go in `tests/Integration/`, end-to-end in `tests/Feature/`.
- Tests must pass in any order. Never depend on state another test left behind, and clean up only what the test created.
- CI runs on Windows too: build expected paths with `DIRECTORY_SEPARATOR`.
- Never write `--` inside an XML comment in a fixture. XML forbids it, and Psalm then reports `Missing psalm node` for the config, which names the wrong problem.
- Test behavior, not characters. Do not chase mutation score on output or rendering code with golden-master tests.
