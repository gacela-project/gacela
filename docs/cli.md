# CLI commands

Gacela ships a console binary. In your project it is `vendor/bin/gacela`; run it with no arguments (or `list`) to see everything with its options.

```bash
vendor/bin/gacela               # list every command
vendor/bin/gacela help doctor   # options for one command
```

Every command except `init` needs a bootstrappable project — a `gacela.php` in the app root. `init` is the one that creates it.

## Scaffolding

| Command | What it does |
|---|---|
| `init` | Creates the `gacela.php` a project needs before anything else works |
| `make:module App/Blog` | Generates a module. `--template=basic\|service\|minimal`, `--minimal`, `--with-tests`, `--short-name` |
| `make:file App/Blog Facade Factory` | Generates named files into an existing module — the four pillars, plus any kind the project declared |
| `stubs:publish` | Copies the scaffolder's templates into the project so `make:*` generates your house style. `--template=basic\|service`, `--force` |

### Your own stubs

`make:module` and `make:file` generate from templates that ship with gacela. `stubs:publish` copies them into the project — `stubs/gacela/` by default, `GacelaConfig::setStubsDir()` to put them elsewhere:

```bash
vendor/bin/gacela stubs:publish                    # every stub
vendor/bin/gacela stubs:publish --template=basic   # one template set
vendor/bin/gacela stubs:publish --force            # replace ones already published
```

From then on a generated file uses the project's stub when there is one and the built-in template when there is not — **per file**, so publishing your Facade stub does not freeze the Factory at the version it was copied from. Without `--force` nothing already published is overwritten: it is a file somebody changed on purpose.

Every stub substitutes `$NAMESPACE$`, `$MODULE_NAME$` and `$CLASS_NAME$`. `doctor` reports a published stub that lost `$NAMESPACE$` or `$CLASS_NAME$`, and one filed under a name the scaffolder does not read — an edit that never takes effect looks exactly like one that did.

### Generating a kind you declared

A kind declared with [`addResolvableType()`](getting-a-dependency.md#resolve-a-kind-of-my-own) is a filename `make:file` accepts, listed in its help next to the pillars:

```bash
vendor/bin/gacela make:file App/Wallet Exporter   # generates App/Wallet/WalletExporter.php
```

Nothing ships for such a kind, so its stub is one you write: `stubs/gacela/exporter-maker.txt`, the kind's name lower-cased and hyphenated where it changes case. Until that file exists `make:file` says so and names the path it looked for — `stubs:publish` has nothing to copy there. `make:module` still scaffolds the four pillars only; a declared kind is generated one file at a time.

## Inspecting a project

| Command | What it does |
|---|---|
| `list:modules` | Renders every module found |
| `debug:modules` | Dependency resolvability of every module pillar |
| `debug:module App/Blog` | One module: resolved classes, container bindings, dependency tree |
| `debug:config` | The effective merged configuration, after every source and override, each key marked `declared`, `undeclared` or `missing` against the [schema](config-schema.md) |
| `debug:container` | Container contents — user bindings and plugins only |
| `debug:dependencies Foo::class` | A class's constructor parameters and whether the container can supply each. `--tree` walks the whole graph and marks every node `binding`, `instance`, `autowired` or `unresolvable` |
| `debug:graph` | The module dependency graph. `--format=text\|mermaid\|graphviz\|json`, `--check` to fail on cycles, `--allowed-cycles`, `--rules` to fail on dependencies your rules file forbids, `--compare-to` |

`debug:graph --check` is the one built for CI; see [module boundaries](module-boundaries.md) for wiring it into a workflow.

## Validating

| Command | What it does |
|---|---|
| `doctor` | Environmental and wiring health checks, including the [declared config schema](config-schema.md) and each package's `composer.json` against what it imports. Takes an optional namespace to restrict module-scoped checks, and `--strict` to exit non-zero on warnings too |
| `validate:config` | Checks the configuration for errors and best-practice violations, and against the [declared schema](config-schema.md) |

`doctor` also runs any check you registered with `GacelaConfig::addHealthCheck()` — see [module health checks](module-health-checks.md).

*config sources* answers whether the paths you passed to `addAppConfig()` match a file. `conf/*.php` for a directory named `config` bootstraps perfectly — globbing a path that is not there yields no files, and an application with no configuration is a legitimate one — and then the first thing to read a key fails, with an error about the key rather than about the path that was meant to provide it. Only the base path is reported: `config/app-prod.php` is *meant* to be absent everywhere it does not apply.

*cache directory* answers whether the cache you enabled can actually be written. Writing is best-effort by design — an application must not fail because an optimisation could not be stored — so a project that enabled caching and got a directory it has no permission on runs correctly and pays the cold cost on every request, with nothing said. Reported read-only: `doctor` never creates the directory to find out.

Among the built-in checks, *service extensions* verifies every `extendService()` id against what the Providers actually `set()`: an extension on an id nothing ever stores — a typo, or an id registered only through `bind()`/`singleton()` — is accepted and applied nowhere at runtime, silently, so `doctor` is the surface that says so. Under `--strict` an unmatched id fails the run.

## Production

| Command | What it does |
|---|---|
| `cache:warm` | Pre-resolves every module class and populates the on-disk caches. `--clear` first, `--attributes` to pre-scan `#[ServiceMap]` |
| `cache:clear` | Removes every Gacela cache file |
| `dto:generate` | Writes the classes declared with `declareDtoSchema()`. `--dry-run` reports without writing. See [DTO schema](dto-schema.md) |
| `ide:meta` | Writes editor metadata typing `getProvidedDependency()` from the `#[Provides]` attributes. `--dry-run` reports without writing. See [IDE metadata](static-analysis.md#ide-metadata) |
| `profile:report` | Performance profiling report — the recording side is the [Profiler](profiling.md) |

`cache:warm` only writes anything when the file cache is enabled; the deploy sequence is in [production performance](production-performance.md).
