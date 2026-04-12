---
description: PHP code style, quality rules, and testing conventions
globs: src/**/*.php,tests/**/*.php
---

# PHP Conventions

## Code Style

- PER 3.0 enforced by php-cs-fixer + rector (auto-formats via PostToolUse hook — no manual run needed)
- PHPStan strict rules, Psalm level 1
- Prefer `final` classes unless inheritance is explicitly needed
- Use `readonly` properties where possible (PHP 8.1+)

## Testing

- Test method names use snake_case: `test_it_does_something()`
- PHPUnit 10.5+ with `--testsuite=unit,integration,feature`
- Unit tests mirror `src/` structure under `tests/Unit/`
- Integration tests in `tests/Integration/`
- Feature tests in `tests/Feature/`

## Quality Gates

```bash
composer quality     # cs-fixer (dry-run) + psalm + phpstan
composer fix         # normalize + cs-fixer + rector (auto-fix)
composer infection   # Mutation testing (requires Xdebug)
```
