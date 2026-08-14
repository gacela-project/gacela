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
| `init` | Creates the `gacela.php` a project needs before anything else works, plus the `config/app.php` it declares. `--force` regenerates `gacela.php` and never touches your config |
| `make:module App/Blog` | Generates a module. `--template=basic\|service\|minimal`, `--minimal`, `--with-tests` (service template only — refused on the others rather than ignored), `--short-name`, `--force` to replace files that already exist, `--dry-run` to see what it would write |
| `make:file App/Blog Facade Factory` | Generates named files into an existing module — the four pillars, plus any kind the project declared. `--short-name`, `--force` to replace files that already exist, `--dry-run` to see what it would write |
| `stubs:publish` | Copies the scaffolder's templates into the project so `make:*` generates your house style. `--template=basic\|service`, `--force`, `--dry-run` |

### Your own stubs

`make:module` and `make:file` generate from templates that ship with gacela. `stubs:publish` copies them into the project — `stubs/gacela/` by default, `GacelaConfig::setStubsDir()` to put them elsewhere:

```bash
vendor/bin/gacela stubs:publish                    # every stub
vendor/bin/gacela stubs:publish --template=basic   # one template set
vendor/bin/gacela stubs:publish --force            # replace ones already published
vendor/bin/gacela stubs:publish --dry-run          # say what it would write, write nothing
```

`--dry-run` works the same way on `make:module` and `make:file`. It resolves the target paths through the code that generates them, so the preview names the files the real run writes rather than a second guess at them — and it refuses where the real run refuses, so a preview over an existing module reports the refusal instead of listing files nothing would write.

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
| `list:modules` | Renders every module found. `--detailed` for one block per module; `--json` for the whole inventory with every pillar, filter and all |
| `debug:modules` | Dependency resolvability of every module pillar. `--detail` for every parameter rather than only the unresolvable ones; `--check` exits non-zero when the container cannot satisfy one, for CI |
| `debug:module App/Blog` | One module: resolved classes, the ids its Provider declares with `#[Provides]`, container bindings, dependency tree |
| `debug:provides ID` | Which Provider declares an id with `#[Provides]`, across every module — the inverse of `getProvidedDependency()`, which answers `null` for an id nothing declares and says nothing about it. The argument narrows to ids containing it; `--json` for a script |
| `debug:config` | The effective merged configuration, after every source and override, each key marked `declared`, `undeclared` or `missing` against the [schema](config-schema.md). `--json` keeps the values as their own types, for diffing one environment against another |
| `debug:container` | Container contents — user bindings and plugins only |
| `debug:dependencies Foo::class` | A class's constructor parameters and whether the container can supply each. `--tree` walks the whole graph and marks every node `binding`, `instance`, `autowired` or `unresolvable` |
| `debug:graph` | The module dependency graph. `--format=text\|mermaid\|graphviz\|json`, `--check` to fail on cycles, `--compare-to`. `--allowed-cycles` and `--rules` are read only by `--check`, and are refused without it |

Two of these are built for CI. `debug:graph --check` fails on a dependency cycle — see [module boundaries](module-boundaries.md) for wiring it into a workflow — and `debug:modules --check` fails when a pillar needs a constructor parameter the container cannot satisfy.

`--check` fails only on parameters the inspector looked at and rejected: an unbound interface, a scalar with no default, a missing type hint, a type that does not exist. A union or intersection type is **not** walked, so it is reported as unresolvable and does not fail the run — that is a gap in the tool rather than a fault in your project. The count of those is printed, so a passing `--check` does not read as "every parameter was checked".

### Where these commands look for modules

Every command above finds modules the same way: it walks the project for `.php` files, loads each one, and keeps the classes that descend from `AbstractFacade`. Hidden directories, `vendor/` and `node_modules/` are skipped; **everything else is descended into**, because guessing that a project's `build/` or `data/` holds no modules is how discovery starts silently missing them.

With no configuration that walk starts at the project root. `setAppModulePaths()` narrows it to the directories your modules actually live in:

```php
// gacela.php
return static function (GacelaConfig $config): void {
    $config->setAppModulePaths(['src']);
};
```

It is worth setting. On this repository, restricting the scan to the directories that hold modules cuts the walk from **14,290 filesystem entries to 1,826** and discovery from roughly **220 ms to 157 ms**, finding the same modules either way — the difference is entirely `docs/`, `tools/`, `build/` and friends being walked and rejected. A project with a large `var/`, `storage/` or `public/` tree saves more.

Paths are relative to the app root. Every module has to be under one of them: a Facade outside the listed paths is not found, and nothing reports it as missing, so widen the list rather than trimming it to the minimum.

This is a CLI and `cache:warm` cost, not a request-time one — resolving a Facade at runtime does not walk anything.

## Validating

| Command | What it does |
|---|---|
| `doctor` | Environmental and wiring health checks, including the [declared config schema](config-schema.md) and each package's `composer.json` against what it imports. Takes an optional namespace to restrict module-scoped checks, `--strict` to exit non-zero on warnings too, `--only-problems` to print just the checks that found something, and `--format=json` for a CI job that wants to say *which* check failed rather than only that one did |
| `validate:config` | Checks the configuration for errors and best-practice violations, and against the [declared schema](config-schema.md). `--strict` exits non-zero on warnings too, as on `doctor`; `--json` reports which check found what |

Every command that can emit JSON accepts `--json`. Where a command has more than two output formats — `debug:graph`, `profile:report` — `--format=` chooses among them and `--json` is shorthand for the one you were going to pick anyway.

`doctor` also runs any check you registered with `GacelaConfig::addHealthCheck()` — see [module health checks](module-health-checks.md).

Seven of the built-in checks report the same kind of fault: **configuration a project declared that nothing acts on**. Each is accepted at bootstrap, costs nothing at runtime, and does exactly nothing — which is why a command has to be the one to say so. Under `--strict` any of them fails the run.

- **event listeners** — a `registerSpecificListener()` target no dispatched event can be. The dispatcher compares `$event::class`, so a listener registered against an interface never fires, not even for events implementing it; nor does one naming an abstract class or a typo. `Container::afterResolving()` matches by `instanceof`, which is exactly why registering against a contract looks like it should work. A concrete class nothing dispatches is left alone — that listener is waiting, not broken. It also reports listeners registered while `disableEventListeners()` is in effect: no dispatcher is built, so every one of them is inert, and that is the first thing to suspect when a listener appears dead.
- **config sources** — an `addAppConfig()` path matching no file. `conf/*.php` for a directory named `config` bootstraps perfectly, and then the first read of a key fails with an error about the key rather than the path meant to provide it. Only the base path is reported: `config/app-prod.php` is *meant* to be absent where it does not apply.
- **cache directory** — caching enabled onto a directory that cannot be written. Writing is best-effort by design, so the application runs correctly and pays the cold cost on every request instead. Reported read-only: `doctor` never creates the directory to find out.
- **duplicate provided ids** — one `#[Provides]` id declared twice on the same Provider. `ProvidesScanner::scan()` `set()`s them in order, so the last wins and every method before it is dead: both read as live, and the only symptom is a value that is not the one the method you are looking at returns. Reported per Provider, because that is where it is unambiguous — each module resolves through its own container, so the same id in two modules is two modules answering for themselves, not a collision.
- **unreachable `#[Provides]`** — the attribute on a private or protected method. `ProvidesScanner` reads public methods only, so PHP accepts the declaration, the method reads as a declared service, and nothing is registered. `getProvidedDependency()` for that id then answers `null`, which is itself silent, so the first sign is a call on null somewhere else entirely.
- **service extensions** — an `extendService()` id no Provider ever `set()`s. A typo, or an id registered only through `bind()`/`singleton()`, which do not drain the extension queue. An `extendProviderService()` id is held against the Provider it names, which is the sharper miss: not "nobody set this id" but "the Provider you named does not", so an id some *other* Provider registers is still reported.
- **tagged services** — a `tag()` id nothing can answer. `Container::tagged()` resolves each id in turn and gives back `null` for one naming nothing, so the group a module iterates carries a hole and the failure lands on the consumer as "Call to a member function … on null", pointing at the loop rather than the registration. An id is answerable when a Provider `set()`s it or it names a class the container can construct, so a tag grouping plain service ids is left alone.

A separate check answers the opposite question — **a module that was never found at all**. Every other check works from the modules discovery returned, so one it skipped is invisible to all of them, and `list:modules` names the cause only when *nothing* was discovered. One broken Facade in fifty leaves forty-nine modules and silence.

The **undiscovered facades** check reports a file named the way the scaffolder names a module's Facade — `Blog/BlogFacade.php`, or `Blog/Facade.php` for `--short-name`, under any configured suffix — that produced no module. Either PHP cannot load the class (a psr-4 prefix that does not cover the directory, a `namespace` disagreeing with the path, a classmap never dumped again) or it loads and does not extend `AbstractFacade`, which is the `extends` nobody wrote on a new module. Those have different fixes, so the remediation names the right one.

It is a warning, not an error: `Facade` is an ordinary word, and a project beside a framework with its own facades should not have a build failed by a naming coincidence. `--strict` is how a project opts in. Only names matching the scaffolder's own pattern count, so a `NullFacade` is left alone here — the [`GacelaSuffixExtends`](static-analysis.md) rule is what has an opinion about naming, and this check is about the module that went missing.

The **unresolved pillar files** check asks the same question one level down. A module whose `BlogFactory.php` cannot be loaded is still a module: the Facade resolves, discovery keeps it, and the Factory simply comes back `null` — so `list:modules` prints a blank cell and `debug:module` says `(not found)`, telling you that you have no Factory while you are looking at the file you wrote. It reports a pillar file that is on disk and resolved to nothing, under any configured suffix and for `--short-name` modules too. A module that genuinely has no Factory is the ordinary case and is left alone; the check is about the *file*.

## Production

| Command | What it does |
|---|---|
| `cache:warm` | Pre-resolves every module class and populates the on-disk caches. `--clear` first, `--attributes` to pre-scan `#[ServiceMap]` |
| `cache:clear` | Removes every Gacela cache file |
| `dto:generate` | Writes the classes declared with `declareDtoSchema()`. `--dry-run` reports without writing; `--check` does the same and exits non-zero when a class is stale, for CI. See [DTO schema](dto-schema.md) |
| `ide:meta` | Writes editor metadata typing `getProvidedDependency()` from the `#[Provides]` attributes. `--dry-run` reports without writing. See [IDE metadata](static-analysis.md#ide-metadata) |
| `profile:report` | Performance profiling report — the recording side is the [Profiler](profiling.md) |

`cache:warm` only writes anything when the file cache is enabled; the deploy sequence is in [production performance](production-performance.md).
