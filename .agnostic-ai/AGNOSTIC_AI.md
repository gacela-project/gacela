# Gacela Framework

PHP 8.3+ modular framework. It splits a project into modules that talk to each other only through a Facade. Each module has four pillars:

- **Facade**: the public API, the only way into the module.
- **Factory**: builds the module's internal services.
- **Provider**: wires external dependencies, such as other modules' Facades.
- **Config**: reads project configuration for the module.

Cross-module access goes through Facades only. Never instantiate another module's classes directly.

## Layout

```
src/                 → Gacela\ namespace
├── Framework/       → Core: Bootstrap, ClassResolver, Config, Container, ServiceResolver, ...
├── Console/         → CLI commands (Symfony Console): cache, make, list, debug, profile
└── PHPStan/         → PHPStan rules for Gacela conventions
tests/               → Mirrors src/
├── Unit/            → Isolated component tests
├── Integration/     → Cross-module interaction tests
├── Feature/         → End-to-end behavior tests
├── Benchmark/       → PHPBench performance tests
└── Fixtures/        → Shared test data
bin/gacela           → The CLI binary (not vendor/bin)
docs/                → User documentation
data/                → Coverage and mutation reports (generated)
```

## Commands

```bash
composer install           # Install dependencies and the pre-commit hook
composer test              # quality + phpunit: the full local gate
composer quality           # normalize, cs-fixer and rector dry runs, psalm, phpstan (src and tests), module cycles
composer phpunit           # unit + integration + feature suites
composer test-unit         # also test-integration, test-feature
composer fix               # Auto-fix: normalize, rector, cs-fixer
composer infection         # Mutation testing (needs Xdebug)
composer test-coverage     # Coverage report into data/
composer phpbench          # Benchmarks (phpbench-base to tag a baseline, phpbench-ref to compare)
```

The pre-commit hook (`tools/git-hooks/pre-commit.sh`) runs `composer quality` and `composer phpunit`. CI also runs `module-graph`, which diffs against the base branch, plus full `infection` and `phpbench`.

| Changed | Run first |
|---------|-----------|
| One class | `./vendor/bin/phpunit --filter=ClassName` |
| `src/Framework/**` | `composer test-unit` |
| Cross-module behavior | `composer test-integration` |
| End-to-end workflows | `composer test-feature` |
| Style only | `composer quality` |
| Anything, before pushing | `composer test` |

`composer infection` writes into fixture directories, and a mangled path can create a directory such as `\\ar/...` (from `/var/...`). It is all gitignored and skipped by rector, but it breaks `composer archive`. Use `git archive HEAD` to see what ships; Packagist builds from it too.

## Protected files

Do not edit `.github/*` or `composer.lock` unless the task is about them.

## Git and pull requests

- Conventional commits: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`. Add `(<scope>)` for a single module. Subject up to 72 characters; explain behavior changes in the body.
- Never mention Claude, AI, or LLM in commits, PRs, or issues.
- Branch prefixes: `feat/`, `fix/`, `ref/`, `docs/`.
- `feat:` and `fix:` changes update `## Unreleased` in `CHANGELOG.md` in the same commit.
- PRs follow `.github/PULL_REQUEST_TEMPLATE.md` exactly, emoji headers included. Assign `@me` and pick one label: `bug`, `enhancement`, `refactoring`, `documentation`, `pure testing`, `dependencies`. One concern per PR. List the validation you ran (at least `composer test`), link the issue, and add screenshots when docs or CLI output change.
- After code changes, give a one-line commit message to copy.
