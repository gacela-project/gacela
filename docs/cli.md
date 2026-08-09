# CLI commands

Gacela ships a console binary. In your project it is `vendor/bin/gacela`; run it with
no arguments (or `list`) to see everything with its options.

```bash
vendor/bin/gacela               # list every command
vendor/bin/gacela help doctor   # options for one command
```

Every command except `init` needs a bootstrappable project — a `gacela.php` in the app
root. `init` is the one that creates it.

## Scaffolding

| Command | What it does |
|---|---|
| `init` | Creates the `gacela.php` a project needs before anything else works |
| `make:module App/Blog` | Generates a module. `--template=basic\|service\|minimal`, `--minimal`, `--with-tests`, `--short-name` |
| `make:file App/Blog Facade Factory` | Generates named pillar files into an existing module — Facade, Factory, Config, Provider |

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

`debug:graph --check` is the one built for CI; see
[static analysis](static-analysis.md) for wiring it into a workflow.

## Validating

| Command | What it does |
|---|---|
| `doctor` | Environmental and wiring health checks, including the [declared config schema](config-schema.md). Takes an optional namespace to restrict module-scoped checks, and `--strict` to exit non-zero on warnings too |
| `validate:config` | Checks the configuration for errors and best-practice violations, and against the [declared schema](config-schema.md) |

`doctor` also runs any check you registered with `GacelaConfig::addHealthCheck()` — see
[module health checks](module-health-checks.md).

## Production

| Command | What it does |
|---|---|
| `cache:warm` | Pre-resolves every module class and populates the on-disk caches. `--clear` first, `--attributes` to pre-scan `#[ServiceMap]` |
| `cache:clear` | Removes every Gacela cache file |
| `profile:report` | Performance profiling report |

`cache:warm` only writes anything when the file cache is enabled; the deploy sequence is
in [production performance](production-performance.md).
