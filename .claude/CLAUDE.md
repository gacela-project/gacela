# Gacela Framework

PHP modular framework that helps separate projects into independently manageable modules using Facade, Factory, Provider, and Config patterns.

## Architecture

```
src/
├── Framework/       → Core: Bootstrap, ClassResolver, Config, Container, ServiceResolver, etc.
├── Console/         → CLI commands (Symfony Console): cache, make, list, debug, profile
└── PHPStan/         → PHPStan rules for Gacela conventions
tests/
├── Unit/            → Isolated component tests
├── Integration/     → Cross-module interaction tests
├── Feature/         → End-to-end behavior tests
├── Benchmark/       → PHPBench performance tests
└── Fixtures/        → Shared test data
```

## Testing

```bash
composer test              # All (quality + phpunit)
composer test-unit         # PHPUnit unit suite
composer test-integration  # PHPUnit integration suite
composer test-feature      # PHPUnit feature suite
composer quality           # Static analysis: cs-fixer, psalm, phpstan
composer fix               # Auto-fix: normalize, cs-fixer, rector
composer infection         # Mutation testing
composer phpbench          # Performance benchmarks
```

### Test Mapping

| Changed | Command | Notes |
|---------|---------|-------|
| `src/Framework/**` | `composer test-unit` | Unit tests first |
| Cross-module behavior | `composer test-integration` | Integration tests |
| End-to-end workflows | `composer test-feature` | Feature tests |
| Single test class | `./vendor/bin/phpunit --filter=ClassName` | Fastest for focused work |
| Any `.php` style | `composer quality` | Static analysis only |
| Mixed changes | `composer test` | Run everything |

## Git

- Conventional commits: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`
- Never mention Claude, AI, or LLM in commit messages
- After code changes, provide a one-liner commit message to copy/paste
- Branch prefixes: `feat/`, `fix/`, `ref/`, `docs/`
- PRs: read `.github/PULL_REQUEST_TEMPLATE.md` and follow exactly (including emoji prefixes); assign `@me`; label from: `bug`, `enhancement`, `refactoring`, `documentation`, `pure testing`, `dependencies`
- Update `## Unreleased` in `CHANGELOG.md` for user-facing changes
