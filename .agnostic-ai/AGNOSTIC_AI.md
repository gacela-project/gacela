# Gacela Framework

PHP 8.3+ modular framework. Each module has four pillars:

- **Facade**: the public API, the only way into the module.
- **Factory**: builds the module's internal services.
- **Provider**: wires external dependencies, such as other modules' Facades.
- **Config**: reads project configuration for the module.

Cross-module access goes through Facades only. Never instantiate another module's classes directly.

## Layout

```
src/                 → Gacela\ namespace
├── Framework/       → Core: Bootstrap, ClassResolver, Config, Container, ServiceResolver, ...
├── Console/         → CLI commands (Symfony Console); see `bin/gacela list`
├── StaticAnalysis/  → Rule logic shared by the PHPStan and Psalm plugins
├── PHPStan/         → PHPStan rules for Gacela conventions
└── Psalm/           → Psalm plugin for the same rules
tests/               → Unit/ mirrors src/; Integration/, Feature/, Benchmark/, Fixtures/
bin/gacela           → The CLI binary (not vendor/bin)
docs/                → User documentation
```

## Commands

```bash
composer install           # Install dependencies and the pre-commit hook
composer test              # the gate: quality + phpunit. Green before every push
composer quality           # normalize, cs-fixer and rector dry runs, psalm, phpstan, module cycles
composer phpunit           # unit + integration + feature suites
./vendor/bin/phpunit --filter=ClassName   # one test class
composer test-unit         # also test-integration, test-feature
composer fix               # Auto-fix: normalize, rector, cs-fixer
composer infection         # Mutation testing (needs Xdebug)
composer phpbench          # Benchmarks (phpbench-base, phpbench-ref to compare)
```

The pre-commit hook runs the gate. CI also runs `module-graph`, `infection` and `phpbench`.

## Git and pull requests

- Conventional commits: `feat:`, `fix:`, `perf:`, `ref:`, `chore:`, `docs:`, `test:`, `ci:`. Add `(<scope>)` for a single module. Subject up to 72 characters; explain behavior changes in the body.
- Never mention Claude, AI, or LLM in commits, PRs, or issues.
- Branch prefixes: `feat/`, `fix/`, `ref/`, `docs/`.
- `feat:` and `fix:` changes update `## Unreleased` in `CHANGELOG.md` in the same commit.
- PRs follow `.github/PULL_REQUEST_TEMPLATE.md` exactly, emoji headers included. Assign `@me` and pick one label: `bug`, `enhancement`, `refactoring`, `documentation`, `pure testing`, `dependencies`. One concern per PR. List the validation you ran (at least the gate), link the issue, and add screenshots when docs or CLI output change.
