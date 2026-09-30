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

## Project Structure

Source code lives in `src/` under the `Gacela\` namespace, grouped by module-responsibility folders. Public CLI tooling sits in `bin/` (notably `bin/gacela`), shared fixtures and sample data in `data/`, and documentation assets in `docs/`. Tests mirror production namespaces inside `tests/` with `unit`, `integration`, and `feature` suites so cross-module behaviour stays isolated.

## More Commands

- `composer install`: install PHP 8.3+ dependencies and trigger repo git hooks.
- `composer quality`: every check CI enforces that is fast enough to run per-commit: composer-normalize, php-cs-fixer and rector dry runs, Psalm, PHPStan over `src/` and again over the tests, then the module-cycle check. `module-graph` stays CI-only because it diffs against the base branch, and `infection` and `phpbench` because they are too slow for a per-commit gate.
- `composer csfix` / `composer csrun`: auto-fix or check formatting with php-cs-fixer config.
- `composer infection`: requires Xdebug enabled. It runs mutated code, which writes files into fixture directories and, when a mutant mangles a path, into a directory named after the mangled root (`\\ar/...`, from `/var/...`). All of it is gitignored and rector is configured to skip it, so a run leaves the suite green. It does break `composer archive`, which filters by `.gitattributes` rather than `.gitignore` and refuses the back-slashed name. Use `git archive HEAD` to inspect what ships; that is also what Packagist builds from.

## Coding Style & Naming Conventions

We follow PSR-12 with 4-space indentation, one statement per line, and trailing commas on multiline arrays. Classes and interfaces use StudlyCase (`ModuleConfig.php`), services use explicit suffixes (`*Facade`, `*Factory`, `*Provider`, `*Config`). Keep constructors typed, return types explicit, and favor `final` classes when extensibility is not required. Run `composer csrun` before pushing; `phpstan.neon`, `psalm.xml`, and `rector.php` codify stricter rules than vanilla PSR guidelines.

## Testing Guidelines

Create tests beside the production namespace and suffix files with `Test.php`. Prefer targeted unit tests that isolate module boundaries, and cover cross-module flows in `integration` or `feature`. Mutation tests (`composer infection`) and coverage reports (`composer test-coverage`) write artifacts to `data/coverage-*`; review reports before merging substantial changes.

## Commits & Pull Requests

Keep the commit subject ≤72 characters and add context in the body when behaviour changes. For pull requests: describe the problem, highlight solution scope, list validation commands (at minimum `composer test`), and link issues or discussions. Include screenshots when updating docs or CLI output. Keep PRs scoped to a single concern to simplify review and release notes generation.
