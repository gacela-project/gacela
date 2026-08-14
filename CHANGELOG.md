# Changelog

## Unreleased

### Added

- Report a `#[Cacheable]` method that never reaches `$this->cached()`, through both PHPStan (`gacela.cacheableWithoutCachedCall`) and Psalm (`GacelaCacheableWithoutCachedCall`), on by default. The attribute is metadata that `cached()` reads, so a method carrying it and not calling it is simply not cached — nothing says so at runtime, and the attribute sits directly above the body claiming otherwise. It judges the class rather than the method, because `cached()` may live in a private helper the attributed method hands off to: that shape is documented, and a rule that could not see the helper would report it as a mistake
- Report `debug:dependencies` as a document with `--json`, the command the other two point a reader at. `parameters` is the shape `debug:modules --json` uses for a pillar and `tree` the one `debug:container --json` uses for a class, so the three commands that describe a class now share one vocabulary rather than inventing one each. `--tree` means what it means in the text report — the section appears only with the flag — and all four refusals (missing class, interface, abstract, a file declaring nothing) answer with a document too
- Report `debug:container` as a document with `--json`, for both of the things it shows: the statistics with the binding map, and a class's dependency tree with its shape kept. It was the last report command answering `The "--json" option does not exist.` — the message the sweep that unified the two spellings existed to remove. `processMemoryBytes` is the number rather than the `10 MB` the text prints, an empty binding map stays `{}` so a consumer indexing it never meets a list, and `total` counts distinct classes while `tree` repeats a class two parents ask for
- Report `debug:modules` as a document with `--json`. It was the one `--check` command with no machine-readable output, so a CI job that wanted to name the pillar and the parameter it failed on had to parse the prose — the gap `doctor --json` and `debug:graph --check --format=json` already close. Each parameter carries its `name` (without the `$` the text prints), `type`, `status` and whether it is resolvable, and `summary` counts `faults` apart from `unresolvable`, which is the distinction `--check` fails on. `--json` alone is not a gate: the verdict is in the document on every run and only `--check` turns it into an exit code
- Report `#[Cacheable]` methods running on the default storage, with `doctor`'s new **cacheable storage** check. That backend lives and dies with the process, so under PHP-FPM a method annotated for an hour's TTL is recomputed every request and the attribute buys nothing — silently, because a cache that misses looks like one never asked to hold anything. A warning rather than an error: the default is right wherever the process outlives the work, such as a CLI batch or a queue worker
- Report a plugin registered in a stack that cannot satisfy it, with `doctor`'s new **plugin stacks** check. `LazyPluginStack` already refuses a class that does not exist and one that does not implement the contract, but on *first resolve* — so a stack nothing has iterated is a registration nobody has checked, and a typo on a rarely-taken path waits until production. The same two questions are asked at deploy time, reported apart: a missing class read as one that "does not implement" the contract sends the reader to inspect an `implements` clause on a file that is not there

- Report a pillar file that is on disk and resolved to nothing, with `doctor`'s new **unresolved pillar files** check. A module whose `BlogFactory.php` cannot be loaded is still a module — the Facade resolves and the Factory comes back `null` — so `list:modules` printed a blank cell while the file sat there. The filename check does not catch it: it compares the declared class name against the filename, and those agree; the namespace is what is wrong

- Fail a `debug:modules` run on an unsatisfiable pillar with `--check`. The command already counted the constructor parameters the container cannot supply and exited `SUCCESS` regardless, so acting on that count meant grepping the output. Only faults fail it — an unbound interface, a scalar with no default, a missing type hint, a type that does not exist. A union or intersection type is not walked by the inspector, so it is reported and deliberately does not fail the run: that is a gap in the tool rather than a fault in the project

- Document `setAppModulePaths()`, which decides how much of a project every CLI command walks. Unconfigured, the module scan starts at the project root: on this repository that is 14,290 filesystem entries against 1,826 when narrowed, same modules found, discovery dropping from roughly 220 ms to 157 ms. It is a CLI and `cache:warm` cost, not a request-time one, so the note sits with the commands and is linked from the deploy step that pays it
- Preview what a scaffolder would write with `--dry-run`, on `make:module`, `make:file` and `stubs:publish`. The preview resolves its paths through the same code generation uses, so it names the files the real run writes, and it refuses where the real run refuses. "Would replace" is said separately from "would create", because losing a file's content is the case a preview exists to check
- Emit the effective configuration from `debug:config --json`, for diffing environments. Each key carries its `declared`/`undeclared`/`missing` verdict against the schema, values keep their own types (`true` a boolean, `42` an int), and a filter matching nothing is an empty list
- Report a module that was never found, with `doctor`'s new **undiscovered facades** check. A file named the way the scaffolder names a Facade that produced no module is reported, naming which fault it is: PHP cannot load the class, or the class loads and does not extend `AbstractFacade`. A warning rather than an error, with `--strict` as the opt-in, and only names matching the scaffolder's own pattern count, so a `NullFacade` or a `FacadeRegistry` is left alone
- Emit the module inventory from `list:modules --json`. Each entry carries the module name, its full namespace and all four pillars, a pillar the module does not have being `null` rather than an omitted key. A filter matching nothing is an empty list rather than a sentence
- Report `validate:config` as a document with `--json` (or `--format=json`), as `doctor` now does. Each check reports its `name`, `status` and findings under an overall `status`, reusing the `ok`/`warn`/`error` values `CheckStatus` already carries. The exit code is unchanged, `--strict` included, and the text output stays byte-identical
- Fail a `validate:config` run on warnings with `--strict`, the bargain `doctor` has always offered. The command reported warnings and exited `SUCCESS` regardless. Errors still fail without it, and a clean run still passes with it
- Report `doctor` as a document with `--format=json`. Every check carries its `name`, `status`, `details` and `remediation`, under an overall `status`; `--only-problems` narrows it exactly as it narrows the text report, and the exit code is unchanged
- Find which Provider declares an id with `debug:provides`. The argument narrows to ids containing it, `--json` carries the same answer, and an id nothing declares says so. Two modules declaring one id keep both rows: each resolves through its own container
- Set a custom event dispatcher from the bootstrap closure with `GacelaConfig::setEventDispatcher()`. `SetupGacela` has always accepted one, but nothing public could reach it. A supplied dispatcher takes precedence over `disableEventListeners()`, which governs the dispatcher Gacela would build rather than one it is given
- Report in `doctor` a `#[Provides]` attribute the scanner never reads: the one on a private or protected method. PHP accepts the declaration, nothing is registered, and the first sign is a call on null somewhere else entirely. The finding names the id, the method and its visibility
- Report in `doctor` one `#[Provides]` id declared twice on the same Provider. The last method wins and every one before it is dead, with both reading as live. The finding names the id, the Provider and every method claiming it
- List what a module's Provider declares in `debug:module`, under `Provides (#[Provides])` and in the `provides` field of `--json`. Attribute-declared ids only, and labelled as such: finding the imperative `set()`s means running the Provider, and this command describes rather than builds
- Answer "are the generated DTO classes up to date?" with `dto:generate --check`. It writes nothing, names each stale file, and exits non-zero, like `debug:graph --check`. Nothing declared is a pass
- Report in `doctor` listeners registered while `disableEventListeners()` is in effect: no dispatcher is built, so every registration is inert. Generic listeners count too, and disabling with nothing registered stays silent
- Name what `profile:report` never saw finish. A `stop()` that misspells the operation or subject vanishes and looks like code nobody instrumented. The report now lists what is still open, and `--format=json` carries the same answer in an `unfinished` field
- Report in `doctor` a tagged id nothing can answer. `Container::tagged()` gives back `null` for it, so the failure lands on the consumer as a call on null, pointing at the loop rather than the registration. An id is answerable when a Provider `set()`s it or it names a class the container can construct
- Report a `#[Cacheable]` key that never mentions the arguments, on a method that has them, through both PHPStan (`gacela.cacheableKeyIgnoresArguments`) and Psalm (`GacelaCacheableKeyIgnoresArguments`). A key without a `{N}` placeholder is the same string for every call, so `getUser(2)` is answered with user 1's row. A key built at runtime is not judged, and a method without arguments is left alone
- Select configuration by more than `APP_ENV` with `addConfigDimension()`: each declared variable adds a link to the chain, so `config/app-prod-eu.php` refines `config/app-prod.php` refines `config/app.php`. The merged-config cache is keyed by the whole tuple, so two regions sharing a cache directory never read each other's file
- Declare a data shape with `declareDtoSchema()` and generate the immutable class from it with `dto:generate`: typed getters, `with*()` copies, `toArray()` and `fromArray()`. Declarations of one shape union, so a project adds a property to a packaged shape without forking it, and redefining one is refused at bootstrap. The file is written where the project's own composer `autoload` already looks
- Declare an extension point with `addPluginStack()`: every implementation of one interface, in declaration order, resolved lazily and read back typed through `getPluginStack()`. Calling it again appends, so another config source contributes to a stack it did not declare
- Wrap a service as one Provider registers it with `extendProviderService()`, leaving every other module that reuses the same id alone
- Declare a class kind of your own with `addResolvableType()`, resolved by suffix like the four pillars and reached through `DeclaredTypeResolverAwareTrait`; the `addSuffixType*()` verbs are now sugar over it
- Generate a declared kind with `make:file App/Wallet Exporter`, from the stub the project publishes for it at `stubs/gacela/exporter-maker.txt`
- Generate editor metadata for `getProvidedDependency()` with `ide:meta`, from the `#[Provides]` attributes. An id two providers type differently is listed rather than written, since one application-wide answer would be wrong in one of them
- Print only the checks that found something with `doctor --only-problems`. Eighteen checks is a lot of "✓" to read to find the one "⚠", and `-q` is not the answer: it suppresses everything, so `--strict -q` fails a build without saying what failed
- Report in `doctor` what a project declared and nothing acts on: an `extendService()` id no Provider `set()`s, a `registerSpecificListener()` target no dispatched event can be, an `addAppConfig()` path matching no file, a cache directory that cannot be written to, a package importing a namespace its own `composer.json` never mentions, and editor metadata the attributes no longer produce. Each of these is accepted today and simply does nothing
- Find the pillar accessors 3.0 removes, with an opt-in rule for both analysers: `Gacela\PHPStan\Rules\ServiceMapMissingRule` (`gacela.serviceMapMissing`) and `<serviceMapMissing/>` on the Psalm plugin (`GacelaServiceMapMissing`). A `@method` accessor on a class using `ServiceResolverAwareTrait`, with no `#[ServiceMap]` declaring it, is resolved by reading the class's own source at runtime — deprecated in 2.0 and removed in 3.0. The runtime notice covers only accessors a run reaches, and only on a cold resolve, so a migration driven by notices is a migration over whichever code paths the tests execute; this reads the same fact from the source, for every class at once. Each finding names the attribute to paste. Off by default, because what it reports is not wrong on 2.x

### Changed

- Name the misnamed file in the "module has no `Factory`" error too. `Factory` and `Config` resolve to an empty stand-in when the module declares none, and the most common reason a module lands on it is not a missing file but a misspelled one — so "Add `WalletFactory`" was an instruction to write a file already in the directory. The same hint the resolver errors carry now follows it, and a module that really has nothing beside it keeps the message it had
- Refuse an output format the command does not have, in `doctor`, `validate:config`, `debug:config` and `profile:report`. Each asked whether the format was `json` and treated everything else as text, so `--format=jsno` printed the human-readable report and exited `0` — a pipeline that asked for a document it never got could not tell that run from a healthy one. `debug:graph` refused it from the start; the message is now the same sentence for all five, and `profile:report --sort` is checked too, both before the early returns that a run with nothing to report takes. **This changes existing behaviour**: a script passing a format that was silently ignored now fails
- Point a "cannot resolve" error at the file already in the module directory. The message said to add the missing class, which is the wrong advice for the two ways this usually fails: the file is named as expected and declares a namespace that does not match where it lives, or it extends the right base class under a name no finder rule builds. Both are now named under the candidate list. Neither is found by comparing names for similarity — every pillar of a module shares its prefix, so a threshold loose enough to catch `WalletProvidr` also offers `WalletFacade` as the missing Provider; the second is read from the file's tokens instead, which also keeps a docblock that merely says "extends AbstractProvider" from being accused of being one
- Skip loading a file where no class extends anything, since it cannot hold a Facade. That is 454 of 1627 classes here, each costing the autoloader a compile the finder then rejects; discovery drops from a 151 ms median to 138 ms (7 runs per side, same 120 modules) on a scan narrowed with `setAppModulePaths()`. The check is deliberately loose, matching `extends` anywhere in the file: a false positive costs one load, a false negative silently drops a module from every command
- Ask whether a class is a Facade before building a module out of it. Discovery built an `AppModule` for every class and discarded it for the ~90% that were not Facades: 1292 constructions to keep 121 on a 121-module project. Discovery drops from a 251 ms median to 238 ms (15 runs each, same 121 modules found)
- Accept `--json` on every command that can emit it. Two spellings had grown side by side, so a reader who learned one met `The "--json" option does not exist.` on the other. `--format` still chooses among the commands that offer a real choice, and the two produce the same document
- Say what an empty module listing means, in `list:modules`, `debug:graph` and `debug:modules` alike. With no argument they blamed a filter the reader never typed; one message now answers for all three and names the cause worth naming: a Facade whose namespace composer cannot map is skipped in silence
- Memoize the resolvable-type normalization in `DocBlockResolver`, cutting service resolution by 10 to 16% on all four `DocBlockResolverBench` subjects (1.130 to 0.968 µs attribute, 1.158 to 0.974 µs phpdoc, rstdev ≤ 2.9%). The suffix-to-kind answer was recomputed on every call, up to three `is_a()` lookups for a fact fixed once the class is loaded. Cleared by `Gacela::resetCache()` like the caches beside it
- Name the class in the docblock-fallback deprecation, spelled `\Fully\Qualified\Name::class`, so the `#[ServiceMap]` line it suggests pastes into any namespace unchanged. The notice printed a literal `className: ...` while reporting it had resolved that very class
- Refuse a `make:file` kind Gacela does not have, instead of generating the closest pillar. `Repository` wrote a `Factory`; `Controller`, `Service` and `Middleware` wrote a `Provider`. Matching is now the letters typed, in order, somewhere in the kind's name, and a word that reaches nothing is refused with the `addResolvableType()` call that would make it real. **This changes existing behaviour**: an undeclared kind used to resolve to a pillar
- Refuse to overwrite in `make:module` and `make:file`, and add `--force` for when replacing is the intent. Every target is checked before the first is written, so a run that would replace something writes nothing and names what is in the way. **This changes existing behaviour**: a script that regenerates a module in place now needs `--force`
- Resolve a class name to its kind through the configured suffixes rather than the four literal pillar names. A project on `addSuffixTypeFacade('PublicApi')` now normalizes `App\Foo\FooPublicApi` to the `Facade` key the resolver looks up, so an `overrideExistingResolvedClass()` call on such a name takes effect where it was inert before

### Fixed

- Stop `#[Inject]` hiding a parameter the container cannot satisfy from `debug:modules` and `debug:dependencies`. The attribute short-circuited the inspection, so it read as proof that something could supply the type: a bare `#[Inject]` on an unbound interface was reported resolvable and `--check` exited `0`, for a module that throws `DependencyNotFoundException` the moment it is built. The attribute names *what* to inject, so it now labels the answer rather than being it — a bare one falls through to the same checks as an unattributed parameter, and one naming a class that does not exist is a fault, which nothing else catches since the container reads that name only when it builds
- Write an array default as one readable expression, in `dto:generate`'s output and in `debug:dependencies`/`debug:modules`. It went through `var_export()`, which puts `array (` on its own line and indents the body from column zero — so a generated constructor call came out across five mangled lines in a file the developer commits, a one-parameter-per-line listing grew line breaks mid-row, and the same text reached the `detail` field of the JSON those commands emit. Now `['a' => 1, 'b' => ['c' => 2]]`, with a list writing its values alone and `null` spelled the way the rest of the generated file spells it
- Generate a `gacela.php` that names the project's own namespaces. `init` wrote the literal `setProjectNamespaces(['App'])` into every project it scaffolded, which is right for the ones that use `App\` and quietly wrong for the rest — the resolver tries those prefixes *before* the module's own namespace, so a wrong one costs a failed lookup on every cold resolution and resolves the wrong class for a project that does have an `App\` module of the same name. The prefixes now come from `composer.json`, falling back to `['App']` when there is no manifest

- Reattach nine docblocks in the shipped code that described nothing, orphaned when a method was added between an existing docblock and its signature. Both halves stay valid php, so the analysers only object when the annotation was load-bearing for a type. A test now walks the shipped directories and fails on any docblock whose next meaningful token is another docblock
- Say whose bindings `debug:module` is listing. The section headed `Bindings` reports the application's container, the same list whichever module is named, so it is now `Application bindings (project-wide)`; `Provides (#[Provides])` above it is the section that answers for the module. The `--json` field keeps its name
- Document the Symfony bridge's `enabled` key: `enabled: false` keeps the bundle in `config/bundles.php` while leaving Gacela un-bootstrapped, and the only way to find it was to read the config tree. A test now asserts every declared key appears in the README, so the next one fails rather than shipping undiscoverable
- Emit a document from `profile:report --format=json` on the two runs that report nothing: profiler disabled, and nothing recorded. Both printed prose, so a JSON consumer got a syntax error exactly when `unfinished` held the only thing there was to say. The text report still says it in words
- List the `autoload-dev` prefixes too when `make:*` cannot match a namespace. The lookup searches `autoload` and `autoload-dev` together, but the `Known PSR-4:` list printed on a miss was built from `autoload` alone, hiding the prefixes the reader needs to spot the typo
- Throw `GacelaNotBootstrappedException` from `Config::getInstance()`, like `Gacela::container()` and `Gacela::rootDir()` already did. It threw a bare `RuntimeException` telling you to call an `@internal` method, and every pillar reaches this state through `Config::getInstance()` first, so catching the typed exception to degrade missed the commonest case
- Register `dto:generate`, `ide:meta` and `stubs:publish` in the Symfony and Laravel bridges, whose hand-written command lists never gained them: `bin/console` and `artisan` could not run commands `vendor/bin/gacela` could, including the `dto:generate --check` a CI job wants. A test in each bridge now reads the command directory and fails when the list falls behind
- Round the per-operation `total_duration` in `Profiler::getStats()` like every other duration in that payload, so float noise like `0.30000000000000004` no longer reaches `profile:report --format=json` next to a rounded `0.3`
- Name the file in "must return a `callable(GacelaConfig)`", and say what it returned instead. The message always said `gacela.php`, so a broken `gacela-prod.php` sent you to the file that was fine, and `include` yields `1` for a file that returns nothing, which is unrecognisable until it is named
- Report the shape of an `--allowed-cycles` file before judging its entries. An object's values were walked as entries, so `{"cycles": [...]}` was blamed at entry level when the file's shape was wrong. Both cases now name the keys found, and say either to wrap a stray entry in `[ ]` or that `--rules` is the flag taking an object
- Report a `#[Cacheable]` key whose placeholders all point past the method's arguments. `{N}` interpolates `$args[N] ?? ''`, so `key: 'user:{5}'` on a one-argument method is a constant, the same wrong-row-served fault `gacela.cacheableKeyIgnoresArguments` already covers. One placeholder in range is enough, and a variadic is not judged on the index
- Hold `APP_ENV` to the same alphabet as a declared config dimension, since it reaches the same config glob and cache filename. Observed: `APP_ENV=../escaped` wrote the merged-config cache outside its directory, and `APP_ENV=x/../../pwned` made the write fail silently, booting uncached with nothing to say why. Both now fail at bootstrap with the message the declared dimensions already give
- Refuse a redefined DTO property when one source declares the same class twice, not only when two sources meet. A second `declareDtoSchema()` call in the same `gacela.php` silently changed a property's shape under every module already reading it. An identical redeclaration and a reworded `describe()` stay allowed
- Name the file in "The PHP config file must return an array or a JsonSerializable object!". A project with several config files got the same sentence whichever one was wrong, and a non-config PHP file returns `1` from `include`, arriving there looking exactly like a typo. `opcache-preload.md` steered readers into this; that page now puts its file outside the config glob
- Stop `validate:config` ticking a binding it just warned about: the green `✓ <key>` after the warning read as the warning being withdrawn
- Stop `debug:container` calling a container empty while reporting what is in it, and print the `abstract => concrete` map instead of only counting bindings. "Container is empty" now appears only when every counter is zero
- Treat a class as a pillar because it **is** one, not because its name contains the word. A service called `ConfigReader` or `FactoryRegistry` was classified as the module's pillar and an anonymous stand-in returned instead of the service, by a route that ignored an explicit `#[ServiceMap]`. Names are still matched by containment, so `FacadeModuleB` and `ModuleBFacade` both still resolve
- Resolve a short class name against the name an import defines, not against the end of the `use` line. A module whose facade is called exactly `Facade` was answered by any neighbouring `*Facade` import, aliases included, and the other module's facade was silently injected. Aliases now bring only the alias into scope, as PHP does, and `use function`/`use const` no longer answer for a class name
- Find a pillar accessor by its `@method` tag, not by the first docblock line that mentions its name. A sentence like `Use getFacade() to reach the module.` resolved `getFacade()` to a class called `wrapper.`, failing as a missing return type on a docblock that states it. A longer accessor no longer answers for a shorter one either (`getFacade` is not `getFacadeExtended`)
- Warm the accessors `cache:warm --attributes` is documented to warm, and stop raising a deprecation per facade while doing it. It resolved real methods, which `__call()` never reaches, so every entry it wrote was one nothing could look up, and it raised the 3.0 deprecation for the inherited `getFactory()`, a call that never takes the docblock path. It now resolves the `#[ServiceMap]` accessors the class does not declare as methods
- Normalize the cache directory on every `getCacheDir()` call, not only the first. The raw value was memoized, so the first caller got `/var/cache` and everyone after it `/var/cache/`, building `/var/cache//file.php` and disagreeing with the empty-directory branch the `doctor` cache checks take
- Never write or delete the scoped cache's dependency graph at the filesystem root. With no usable cache directory, `ScopedCache` persisted the graph to `/` on every `dependsOn()` and unlinked it on `clear()`. It now reads the same `CacheFilePath` rule the values do, and keeps the graph in memory when there is nowhere to put it
- Refuse `debug:graph --rules` and `--allowed-cycles` when `--check` is absent, instead of ignoring them: both files are read only by `--check`, so the run was a gate that looks green while checking nothing
- Refuse `make:module --with-tests` on a template that scaffolds no test, instead of accepting the flag and reporting success, so a reader who asked for a test believed they had one
- Report in `doctor` an `extendProviderService()` id the named Provider never `set()`s, as its own docblock promised. Only app-wide `extendService()` ids were ever checked, so a typo or the wrong Provider named applied nowhere and said nothing
- Answer a mistyped Laravel bridge config key with the one that was meant, as Symfony's config tree does, reusing the helper Gacela already reads for a mistyped `gacela.php` key
- Say so when a plugin's class does not exist. A typo in `addPluginStack()` resolves to `null` and was reported as a class that "does not implement" the contract, sending the reader to inspect an `implements` clause on a file that is not there
- Never touch the filesystem root from `FileCache` when there is no cache directory. `''`, `'/'` and whitespace all normalize to an empty directory, so entries landed at `'/<sha1>.php'` and `clear()` would have unlinked every PHP file it found at the root. `CacheFilePath` now answers "nowhere" rather than "the root", honoured by reads, writes, deletes and batch commits
- Name the cause when `assertServiceResolved()` or `assertBindingRegistered()` has no events to read. Only `bootstrapGacela()` registers the collecting listener, so a test keeping its own `Gacela::bootstrap()` call was told its service "was not resolved" when nothing was watching
- Allow a Facade to delegate to a kind declared with `addResolvableType()`. The shipped `FacadeOnlyDelegates` rule knew only the three pillar accessors, so `return $this->getResolvedType('Exporter')->run();` was reported as inline logic. Two shipped features contradicting each other
- Stop reporting a class that already extends something else as needing to extend a pillar. `gacela.suffixExtends` told an OAuth `GoogleAuthProvider extends AbstractOAuthProvider` to extend `AbstractProvider`, which single inheritance makes impossible. Gacela's own Laravel bridge carried an ignore entry for exactly this, now removed
- Stop reporting a Facade's static methods as failing to delegate: a static method has no `$this` to reach `getFactory()` through, so no body it could hold would satisfy `gacela.facadeOnlyDelegates`
- Stop reporting a Facade's static methods as missing from its interface: a static is not reached through an instance, so requiring it would force every implementer, test doubles included, to carry a static factory. Magic methods were already excluded on the same grounds
- Carry the class-not-found tips on the two exceptions raised when one is named: a health check registered through `addHealthCheck()` and the base class of a kind declared through `addResolvableType()`. The advice for exactly that case existed and was reachable from nothing
- Advise on the kind that failed to resolve. A missing `Provider` named `Provider` four times and then closed with "Ensure your Facade extends AbstractFacade", and a declared kind is no longer offered an `Abstract*` base the framework does not ship
- Name the module-prefixed class in the resolver's `E.g.` line, matching what `make:module` writes and the first candidate the finder tries
- Name the config key that was meant when one is not found. `ConfigException::keyNotFound()` has always accepted the available keys and always been handed none; all six typed getters now supply them, and a nested reach like `database.host` is answered with `database`
- Declare `psr/container` in both bridge manifests and `symfony/console` in the Laravel bridge's, and demote their test-only requirements to `require-dev`, so each manifest states what its `src/` imports and only that
- Key the on-disk class-name cache by the bootstrap's `projectNamespaces` and suffix types, so two bootstraps of one app sharing a cache dir no longer serve each other's classes
- Point the "missing return type" exception at the namespace where `ServiceMap` lives: its recommended fix named one that does not exist. Its docblock alternative is now marked as the deprecated path it is
- Name the module and the file when a `Factory` or `Config` a module never declared is called. Both resolve to an empty stand-in, so the first call reported `Call to undefined method Gacela\Framework\AbstractFactory@anonymous::createThing()`, naming neither the module nor what to add. `Provider` has said it properly all along, having no stand-in to fall back to
- Refuse a `make:module` or `make:file` path whose segments cannot be PHP names, before writing anything. `App/user-profile` generated a module that does not parse and reported "created successfully", with the error pointing at the generated file rather than the name that caused it. Accented and non-latin names are still accepted, being valid identifiers
- Create `config/app.php` in `init`, alongside the `gacela.php` that declares `config/*.php`, so `doctor` on a fresh scaffold no longer reports a config path loading nothing. `--force` regenerates `gacela.php` only: the configuration beside it is the project's own
- Preload the framework and the packages it runs on, discovered rather than named in a list. A preloaded class is only kept if everything it extends, implements and uses came along, so the hand-written list silently linked none of the pillars, the resolvers or either container
- Read `gacela.php` once when bootstrapping without a closure, instead of loading it as a config source it is already the source of. Everything the project declared arrived twice: one `addAppConfig()` globbed twice, a plugin declared once ran twice. Passing a closure to `Gacela::bootstrap()` was never affected

### Internal

- Clear PHPStan's result cache before the fixture tests analyse. It is keyed on the analysed files and not on the rules that judge them, so editing a rule and re-running those tests could be answered from before the edit — a rule that had stopped reporting still looked green. Psalm's harness beside it already passed `--no-cache`; PHPStan has no such flag. The clear is checked rather than fired and forgotten, because the first attempt passed an option `clear-result-cache` does not accept, failed, and was swallowed
- Drive every default-on PHPStan rule from a test. Three of the five — `SuffixExtendsRule` (registered four times, once per pillar), `FacadeInterfaceInSyncRule` and `FactoryDoesNotCallFacadeRule` — had the analyser's unit tests and, for some, a Psalm front end, and nothing reaching the PHPStan rule itself. A rule whose adaptation is broken analyses clean, which looks exactly like code with nothing wrong with it. Found by silencing each analyser in turn and seeing which tests noticed; that audit has to clear PHPStan's result cache between rounds, or the child process answers from before the change and the verdicts flip
- Give the method-level static-analysis rules the interface the class-level ones have. `MethodAnalyserInterface` is the sibling of `ClassAnalyserInterface`, and it exists for the reason that one gives: one shape lets a host adapt them as a list instead of once per rule. Without it the two were named individually in both hosts — a property, a `??=` and a loop each in the Psalm plugin, and a PHPStan `Rule` copied from its sibling. Adding `FacadeOnlyDelegatesRule`'s missing PHPStan test while verifying this closed a real gap: it is registered uncommented in `phpstan-gacela.neon`, so every consumer runs it, and no test drove the PHPStan front end for it
- Move the bridge bootstrapping into `Gacela\Framework\Bootstrap\IntegrationBootstrapper`. Both bridges held a copy of it that was byte-identical once the docblocks were stripped, and none of it was specific to a framework — including the `resetInMemoryCache()` call that keeps a host booting twice in one process from serving the previous boot's container, which each bridge had to learn separately. The host is reached through a closure rather than a PSR-11 container, so this carries no dependency an integration might not have. Each bridge keeps its own `GacelaBootstrapper` name and constructor, now an adapter
- Delete `ContextualBindingRegistrar`. It narrowed the stored `mixed` before handing it to the container's `give()` — class strings and objects through, anything else wrapped in a closure — and its only remaining reason was that `give(null)` threw upstream. That shipped in container 2.0 (container#169), so the class had become a pass-through. All nine implementation shapes were compared through it and around it before removing it, and its tests were rewritten against the call it used to make rather than deleted with it

### Documentation

- Correct the remedy offered for a misspelled provided-dependency id. "Check `debug:module`, which lists every id that module's Provider declares" does not hold: the command lists what `#[Provides]` declares and not what a Provider registers imperatively with `$container->set()`, so the module the advice was written for prints `(none)` however many ids it has. Declaring with the attribute is what makes an id visible to tooling, which is now what the page says

## [2.2.0](https://github.com/gacela-project/gacela/compare/2.1.0...2.2.0) - 2026-08-11

### Added

- Declare which modules may depend on which, in one JSON file read by `debug:graph --check --rules` and by both analysers. `--format=json` writes the findings for a CI job that wants more than an exit code, and a rule that governs no module is reported, so it cannot outlive what it was written about
- Declare what the configuration must contain with `declareConfigSchema()`, read by `validate:config`, `doctor` and `debug:config`. A declared default fills a key no config source provides, without ever overriding one that does; `debug:config` marks every key `declared`, `undeclared` or `missing`; and `validateConfigSchemaOnBoot()` checks the schema on every bootstrap, for local development
- Replace another module in a test with `swapModuleFactory()`, `swapModuleConfig()` and `swapModuleProvider()`, dropped again in `tearDown()`
- Register `GacelaBundle` in a Symfony application, straight out of the framework package: it bootstraps Gacela from the kernel, maps listed Symfony services into it, adds the console commands under a `gacela:` prefix, and warms Gacela's caches from `cache:warmup`
- Register `GacelaServiceProvider` in a Laravel application: the same four things against the application's boot, listed Laravel bindings, the artisan commands and `artisan optimize`. It also honors `#[Inject]` on Laravel-resolved services: on constructor parameters through Laravel's contextual attributes, on properties and setters through `afterResolving`
- Publish the scaffolder's templates into the project with `stubs:publish`; generation reads them per file and falls back to the built-in ones. `doctor` reports a published stub that lost a placeholder, or one the scaffolder never reads
- Register `phpstan-gacela.neon` through `phpstan/extension-installer`, so requiring Gacela is the whole PHPStan setup. Opt out per package with `extra."phpstan/extension-installer".ignore`

### Changed

- Resolve a `#[ServiceMap]` accessor once per class and method under PHPStan. It asks `hasMethod()` before `getMethod()`, so the attribute was read at least twice for every typed accessor

### Fixed

- Ship the Symfony and Laravel bridges to the people installing Gacela. `.gitattributes` stripped both directories from the dist archive, and their namespaces sat in `autoload-dev`, so nothing under `Gacela\SymfonyBridge` or `Gacela\LaravelBridge` reached a consumer. `GacelaInjectCompilerPass` has been unreachable that way since it shipped in 1.14.0
- Serve the rebooted kernel's services after a second `GacelaBundle` boot, instead of the previous kernel's out of a stale locator
- Type a `#[ServiceMap]` accessor whose mapped class PHPStan knows but the analysing process cannot autoload. The extension asked `class_exists()`, so the mapping was dropped and the accessor silently went back to being untyped

## [2.1.0](https://github.com/gacela-project/gacela/compare/2.0.0...2.1.0) - 2026-08-09

### Added

- Run the Gacela architecture rules under Psalm too, each as its own suppressible issue type
- Type `getProvidedDependency(Foo::class)` under Psalm, matching the PHPStan extension
- Report cross-module calls on injected dependencies, which name no class at the call site
- Show the correction next to every rule finding, not only the complaint

### Changed

- Resolve graph imports through a name index instead of comparing every import to every module
- Prune `vendor`, `node_modules` and hidden directories before descending in module discovery
- Exit non-zero from `cache:warm` when a warmup fails, so a broken deploy is not reported green

### Removed

- Drop the abandoned `gacela-project/phpstan-extension` suggestion; its rule is now built in

### Fixed

- Never tell an interface, trait or enum to extend a pillar, which PHP does not allow
- Resolve imported class names the way each host does; under Psalm a `use`d class matched no module
- Strip only the matched psr-4 prefix in `make:module`/`make:file`, and accept list targets
- Let `bin/gacela` run from any subdirectory, bootstrapping with the project root
- Discover facades that extend `AbstractFacade` indirectly, not only direct children
- Parse graph imports with the tokenizer: grouped, multiline, aliased and `use function`/`use const`
- Check the merged configuration cache against its sources in `doctor`
- Validate config without constructing services, so `validate:config` has no side effects
- Treat `ttl: 0` as no expiry in `InMemoryCacheStorage`, matching `FileCache`
- Inherit app-wide resolution hooks in module scopes, for `get()`, `getOrFail()` and `make()`
- Record nested and recursive profiler spans, and drop in-flight ones on `disable()`
- Preserve duplicate module health checks and aggregate them to the worst reported level
- Restore config values, app root and cache dir in `ContainerFixture::restoreContainerState()`

## [2.0.0](https://github.com/gacela-project/gacela/compare/1.21.0...2.0.0) - 2026-08-07

The "one container" release. Module containers are scopes of one app container, so `gacela.php` is walked once per bootstrap instead of once per Factory class: 79 containers against 300 app-wide entries go 18.0ms → 0.07ms.

Two runtime changes: PHP `>=8.3`, and `gacela-project/container` `^2.0.2` up from a `0.x`. Most of what follows comes from the second: `#[Lazy]`, `#[Inject]` on properties, PSR-11-correct `has()`, and container exceptions where 0.x emitted raw PHP errors.

Migration is three mechanical renames. See [UPGRADE.md](UPGRADE.md). Run `vendor/bin/gacela doctor` on 1.21 first, because one of the three fails silently.

### Added

- `GacelaConfig::loadDefinitions()` registers a definition set from an array or a `.php`/`.json` file, app-wide like `addBinding()`. `Container::load()`/`loadFile()` are forwarded, so a Provider can scope definitions to one module. Sources apply after the imperative registrations, so a file overrides `addBinding()`; tags accumulate instead. Paths are used as given, so write them with `__DIR__`. No YAML: pass `Yaml::parseFile(...)`
- `GacelaConfig::afterResolving()` runs a callback on a resolved instance. The id may name an **interface**, so one registration covers every implementation. It fires on `get()`, `getOrFail()` and `make()` in registration order, **once per resolution, not once per instance**, so write callbacks that are safe to repeat. It does not fire for a nested constructor dependency, and one that throws evicts the instance
- `GacelaConfig::tag()` groups services under a label, reaching every module's container; a module adding to a tag in its own Provider stays local to it. Use `tag()` for an unkeyed set you iterate, `addHandlerRegistry()` for a keyed lookup that throws on a miss
- The dependency tree is an actual tree. It comes from the container, so bindings and contextual bindings are already applied, and a missing dependency says *whose* it is. A cycle is marked `(cycle)` and cut, and counts are of distinct classes. `debug:dependencies --tree`, `debug:module` and `debug:container` all draw it; the latter two printed a flat list under a heading that said "tree"
- A Psalm plugin (`Gacela\Psalm\Plugin`) types the pillar accessors from `#[ServiceMap]`, the counterpart of the PHPStan extension. Before, `psalm-gacela.xml` only *suppressed* the error, so the accessor evaluated to `mixed`
- One constructor-plan cache for every container. Gacela's containers are sibling roots configured from the same `gacela.php`, so each used to reflect the same classes again: ten containers resolving one four-level chain drop **~36%**, measured on CI at 90.3μs → 58.0μs. Only reflection output is shared; bindings, aliases, tags, singletons and stored instances stay private to each container. Pass your own `PlanCache` as the container's fourth argument to opt one out
- `Gacela\Framework\Attribute\Inject`, so application code no longer imports a vendor namespace for its one attribute-first surface. It subclasses the container's attribute, which works because attributes are read with `ReflectionAttribute::IS_INSTANCEOF`. Not a `class_alias()`, as first planned: an exact-FQN read follows neither an alias nor a subclass, and the failure is **silent**
- `#[Inject]` targets properties and setters, not only constructor parameters, so it covers classes whose constructor is not yours to change
- `#[Lazy]` joins `#[Inject]`, `#[Singleton]` and `#[Factory]` as an attribute honoured by `AbstractFactory::make()`
- `cache:clear` also clears the container's in-process memos: reflection output held in statics that outlive every container, which no file holds
- `Container::provides()`, `taggedByKey()`, `taggedKeys()`, `lazy()`, `writeCompiledCache()` (now with a build-stamp argument), `writeCompiledFactories()`, `useCompiledFactories()` and `compileReport()` are forwarded. Nothing here calls the compilation methods: writing plans to disk measured a **net loss**, 1.008ms to load a 300-class plans file against 0.233ms saved. They are reachable for an application that has measured its own case

### Changed

- **Module containers are scopes of one app container.** `AbstractFactory` built one per Factory class through `Container::withConfig()`, and that walks the whole of `gacela.php` (every binding, factory, alias, contextual binding, tag and hook) once for each. 79 walks in this repository alone, and the cost grows with an application's wiring, not with its number of modules. Each module now gets a scope of one shared app container, carrying only its own Provider. Isolation is unchanged: registration is not copied and a miss falls through, so a module still cannot see a sibling's provider keys. `extendService()` is app-wide configuration that often decorates a service a module's Provider registers, so each scope schedules those itself, skipping ids the parent owns. 79 containers against 25 app-wide entries go 1.55ms → 0.06ms; against 300 entries, 18.0ms → 0.07ms. An application with little configuration sees none of that, and pays ~2.5% on warm class resolution for the fall-through
- **`gacela-project/container` `^2.0.2`** (was `^0.10.0`). `Container` is `final`, so Gacela decorates it by composition, and the whole surface is now on `ContainerInterface`, where an unforwarded method is a compile error instead of something silently unreachable. `withSelfReference()` removes the closure-wrapping layer, roughly 50 lines across 29 touchpoints, and the `WeakMap` leak with it. `createScope()` is forwarded, so a scope is a decorator like its parent and keeps the Locator and the lifecycle events. `load()`/`loadFile()` return the ids they registered and take an optional per-id callback, so definitions emit `BindingRegisteredEvent` like every other registration. Two upstream fixes land in Gacela's own path: a class-string sharing a name with a function was *invoked* instead of instantiated, and `has()` remembered a negative, so a class declared after the first probe stayed invisible. One caveat, reported rather than absorbed quietly: container 2.0.0 resolved 11-28% slower **cold** than 1.5.0 ([container#181](https://github.com/gacela-project/container/issues/181)), because the per-class argument builder was composed on a class's first resolution. Container 2.0.1 defers that to the second construction, and the four benchmarks that show it now measure +2.1 to +3.5% over 20 paired samples. Gacela's own benchmarks never moved
- **`ContainerStats::memoryUsageFormatted()` is `processMemoryFormatted()`**, and `debug:container` labels it **Process Memory**, which is what it always measured
- **`ConsoleFacade::getContainerStats()` and `ConsoleFactory::getContainerStats()` return `ContainerStats`** instead of an array
- **PHP floor raised to `>=8.3`** (was `>=8.1`). 8.1 is end of life, and 8.2's security window closes in December 2026
- **`symfony/*` widened to `^7.0 || ^8.0`** (was `^6.4`), so Gacela no longer decides a consumer's Symfony major
- **The PHPStan suppression for undeclared pillar accessors is gone.** An accessor you have not declared is reported, not silently typed `mixed`
- Class constants on `AbstractSetupGacela` and `ConfigInterface` declare types, so a subclass redeclaring one is checked at compile time

### Fixed

- **`symfony-bridge` was outside every static analysis tool and the coverage gate.** phpstan, psalm, php-cs-fixer, rector and phpunit's `<source>` all scoped to `src/` and `tests/`, so only its 7 tests ran and nothing ever looked at the file. That is how the `#[Inject]` subclass bug reached `main`, with `GacelaInjectCompilerPass` reading the attribute by exact FQN and silently dropping every `Gacela\Framework\Attribute\Inject`, and how the disjoint `gacela-project/container: ^1.4.0` constraint survived. The bridge is under all five tools plus coverage and the mutation gate now, at 100% MSI
- **`doctor`'s filename check could not see the mismatch it exists to catch.** `FilenameMismatchCheck` drove off resolved pillar classes, and a class whose file basename does not match its short name does not autoload under PSR-4, so the pillar came back `null` and `doctor` reported "every pillar class matches its filename" and exited 0 on exactly the module whose provider silently never runs. It only had teeth under classmap autoloading. It now also reads the module directory, located from the facade, and compares each file's declared class against its basename with a token scan. Both rename directions are covered
- **`Gacela::resetCache()` flushed a `#[Cacheable]` backend the application registered.** It reached `CacheableTrait::clearMethodCache()` through `AbstractFacade::resetCache()`, and that calls `clear()` on whatever storage is configured. So on any application that followed `docs/caching.md` and wired APCu or Redis, resetting Gacela's caches emptied the whole store. It bit hardest in test suites, where `GacelaTestCase::bootstrapGacela()` resets once per test. It now clears only the framework's own in-memory default; calling `clearMethodCache()` yourself still clears everything, as its docs say
- **A second `Gacela::bootstrap()` served the first one's config.** `ConfigFactory` memoized the merged config in a `private static` and returned it before looking at the setup it was constructed with, so re-bootstrapping in one process kept the first bootstrap's merged config, bindings included. That is the scenario `UPGRADE.md` names for long-running workers: RoadRunner, Swoole, queue consumers. The memo is now keyed on the app root and the setup instance it was built from, so a different config rebuilds and an identical one still hits the memo. Present since 1.0.1
- **The container retained every closure it was ever handed.** The mark that stops a wrapper being wrapped twice held its keys strongly and was never cleaned, so `set()`, `bind()`, `extend()`, `factory()` and `protect()` leaked their closures and everything each one captured. Overwriting one id 5000 times held 6.1 MB for a single live binding; it is 1.8 KB now
- **Two applications sharing the default cache directory served each other's resolved class names.** The cache dir defaults to the system temp dir, so `gacela-class-names.php` and `gacela-custom-services.php` were written under that one name whatever project they belonged to. Both filenames now carry the app-root hash the merged-config cache already used, and `cache:clear` removes the unscoped spelling too, so a file written before the upgrade cannot keep answering
- **`CacheWarmedEvent` reported skipped modules as failed**, so a listener alerting on `failedCount() > 0` fired on a successful deploy. A missing pillar class and one whose autoloading threw now count separately, `skippedCount()` reports the healthy one, and `cache:warm` gains a `Classes failed:` line
- **`make:module` and `make:file` failed on a brand-new project.** `FileContentIo::mkdir()` omitted the recursive flag
- **`Container` emitted PHP 8.5 deprecation notices on a core path.**
- **The in-memory copy of the file-backed caches survived `Gacela::resetCache()`.** Entries read from disk kept answering after a reset
- **`extendService()` on an id naming an autowirable class threw**, instead of scheduling the extension
- `Gacela::resetCache()` drops the memoized "this class does not exist" answers

### Deprecated

- Resolving a pillar from a `@method` docblock, or by scanning the caller's `use` statements, raises `E_USER_DEPRECATED`. Both are removed in 3.0. Declare it with `#[ServiceMap]`

### Removed (BREAKING)

- `AbstractDependencyProvider`. Extend `AbstractProvider` instead
- `GacelaConfig::addMappingInterface()`. Use `addBinding()`
- `DocBlockResolverAwareTrait`. Use `ServiceResolverAwareTrait`
- Internal `DependencyProviderResolver`, and the `AbstractFactory` dual-resolver path. `*DependencyProvider` classes are no longer auto-resolved. Rename them

### Documentation

- New `UPGRADE.md`: the 1.21 → 2.0 migration, ordered by how likely each change is to hit you. The PHP floor, the three renames, then static analysis
- New `docs/getting-a-dependency.md`: one primary path per intent, with the rest listed alongside the situation where each one is right

### Internal

- **`phpunit/phpunit` is `^12.5`, and `rector/rector` installs into `vendor-bin/rector/`** via `bamarni/composer-bin-plugin`. The two are one change: rector ships a composer `files` autoload that requires its own bundled `nikic/php-parser` once PHPUnit 12 is present, and this package requires `nikic/php-parser` directly for its PHPStan rules, so whichever loaded first the other redeclared `PhpParser\NodeVisitor`, a fatal thrown from the autoloader before the first test ran. Separate autoloaders keep the two copies apart. `composer install` still fetches it (`forward-command`), and the `rector`/`rectorrun` scripts and the Code style workflow point at the new path. The bump also unpins ~22 transitive packages that `phpunit 10.5` was holding back
- The PHPUnit 12 migration surfaced three things worth fixing rather than silencing. Two `SetupGacelaTest` cases compared closures with `assertEquals`, which only passed because PHPUnit 10's comparator looked no further than the type; they now run the extensions and assert the result, which pins the merge *order* too. Twenty-four `createMock()` calls with no configured expectations became `createStub()`. And `ClassNameFinderTest::test_rule_but_no_resolvable_types` described a call that never happens, so it now asserts the validator is never consulted
- Two coverage tests guard cache resets from both ends: `ResetCacheCoverageTest` checks that a declared reset is reached by `Gacela::resetCache()`, and `StaticStateCoverageTest` that every `static` property under `src/` is back at its declared default afterwards, or listed with a reason
- `ContainerForwardingCoverageTest` requires every public method of `Gacela\Container\Container` to be forwarded, or listed with its reason. Implementing `ContainerInterface` never gave this for free, since 1.x promised never to extend the interface: every capability since 1.0 landed on the concrete class, where an unforwarded method compiles fine and stays unreachable. The exemption list is empty now, `createScope()` having been its last entry
- `AttributeReadCoverageTest` requires every read of a non-`final` attribute to pass `ReflectionAttribute::IS_INSTANCEOF`. An attribute is left non-`final` precisely so it can be subclassed, and a reader naming the parent without the flag matches the parent and nothing else. That is how `Gacela\Framework\Attribute\Inject` was honoured by the container and by nothing else: the parameter showed as plain autowiring, and Symfony autowired it. What hid it is that the container's own three reads were already correct, and every test went through `Container::make()`. `debug:dependencies`, `debug:module`, `debug:container` and the Symfony bridge pass the flag now, each with a test that reads through the surface rather than the container
- `Gacela::resetCache()` clears the shared plan cache, so plans are shared *within* a bootstrap rather than across one. It deliberately does **not** call `Container::resetStaticCaches()`: that was tried, and cost `FileCacheBench` 11-17%, for memory rather than correctness
- `DocBlockResolverCache` is `ServiceResolverCache`. Nothing about it is docblock-specific, and every symbol it touches said so already (`CustomServicesPhpCache`, and the three `CustomServices*` events); the name now matches its counterpart, `ClassResolverCache`. Internal, so no migration
- The container's constructor-plan type aliases moved to `PlanRegistry`, which holds them, from `DependencyResolver`, which builds them. That symbol only exists from container 2.0.2, which is why the floor is `^2.0.2` and not `^2.0.1`
- `debug:container` reads `stats(): ContainerStats`, instead of the untyped `getStats()` array whose shape upstream excludes from BC
- Infection 0.34's `ReturnRemoval` mutator reads every early `return` as behaviour. Both jobs run `--show-mutations=max`, since the default caps the list at 20 and hid 13 of 33 escapes
- Raised the `nikic/php-parser` floor to `^5.4`: Psalm 6.16 reads `Property::$hooks`, which only exists from 5.4
- Refreshed the toolchain: `infection` `^0.29` → `^0.34`, plus phpstan and rector. `psalm/plugin-phpunit` stays on `^0.19`; `0.20` requires `psalm/psalm-plugin-api ^0.1`, which conflicts with `vimeo/psalm <7.0.0`, and Psalm 7 is still at `7.0.0-beta19`
- That rector refresh left `rector.php` naming `SetList::STRICT_BOOLEANS`, which 2.x removed, so `composer rector` and `composer fix` both died on an undefined constant and the entire ruleset went unapplied. Nothing caught it: rector was in neither `composer quality` nor any workflow. It is in both now, as `composer rectorrun`, alongside `phpstan-tests`, which was also configured and also never run in CI. Re-applying the ruleset touched 60 files, all internal; the only `src/` signature changes are nine `private static` methods becoming `private`. `composer fix` also ran `csfix` before `rector`, so rector's own output went unformatted and left the tree failing `csrun`. Reordered
- Six dead-code rules and `ReadOnlyPropertyRector` are bounded to `src/`. Fixtures are shaped on purpose, and dead-code removal reads that intent as waste: it stripped `stream_close()` and `stream_open()`'s by-ref `$openedPath` from a stream wrapper PHP calls by contract, emptied the fixtures named `EmptyConstructorService` and `UntypedAndUnionService`, and dropped the assignments keeping a benchmark's subject from being optimised away. `StringClassNameToClassConstantRector` is off for `CacheClearCommandTest`, whose docblock already said why it names an `@internal` class as a string
- `LevelSetList` deliberately stays at `UP_TO_PHP_81`, under the `>=8.3` floor. Going to 8.3 turns 60 changed files into 223, and what it adds is BC-hostile: `ReadOnlyClassRector` alone marks 103 classes `readonly`, which a non-readonly child may not extend, and that is every downstream `AbstractFacade`, `AbstractFactory`, `AbstractConfig` and `AbstractProvider`
- A root `gacela.php` scoping the console to `src`. Without it, `doctor` walked the whole repository and reported two errors on a clean checkout: `tests/` holds fixtures that are deliberately separate applications, several declaring their own pillar suffixes
- `symfony-bridge/composer.json` matches the root package it ships from
- Removed Scrutinizer. It duplicated PHPStan, Psalm and php-cs-fixer, which already gate every pull request

## [1.21.0](https://github.com/gacela-project/gacela/compare/1.20.0...1.21.0) - 2026-07-26

Static analysis now **types** the pillar accessors instead of suppressing them, and the console gained checks for mistakes that were previously silent. Both are worth having before you migrate to 2.0.

### Added

- `gacela init` scaffolds a project's `gacela.php`, the one file you had to copy from the docs before anything ran. Refuses to overwrite unless `--force`
- PHPStan **types** `getFacade()`/`getFactory()`/`getConfig()` from `#[ServiceMap]` instead of suppressing them. A suppressed call evaluated to `mixed`, which switched off checking of everything reached *through* the accessor. Nothing to configure beyond the existing `phpstan-gacela.neon` include; the suppression stays as a fallback for classes declaring neither `#[ServiceMap]` nor a `@method` docblock, and goes away in 2.0
- PHPStan types `getProvidedDependency(Foo::class)` as `Foo`, so the class-string form needs no hand-written `@var`. String keys stay `mixed`
- `FacadeInterfaceInSyncRule` reports public Facade methods missing from the Facade's own `*FacadeInterface`, drift that is invisible until someone reads both files, by which point the fix is breaking. Only fires for a facade that implements the interface named after it
- `doctor` reports pillar classes whose filename does not match the class. Pillars resolve by *filename* suffix, so migrating `AbstractDependencyProvider` means renaming `DependencyProvider.php` to `Provider.php` too; miss it and the module silently stops resolving
- `doctor --strict` exits non-zero on warnings as well as errors, so it can gate CI
- `debug:graph --check` exits non-zero on a module dependency cycle. `--allowed-cycles=file.json` records the ones a reviewer accepted, each with a mandatory `reason`, and an entry that no longer matches a real cycle fails just as loudly: an allow-list that outlives what it allows is a mute button. Without `--check` the command stays exit-code-neutral
- `debug:graph --compare-to=base-graph.json` diffs against a previously captured graph and reports the change as markdown with a mermaid diagram. Writes nothing when the graph is unchanged, so CI comments only when a pull request actually moves a boundary

### Changed

- `profile:report --format=json` and `debug:config` raise a `JsonException` instead of printing an empty value when their payload cannot be encoded
- `validate:config` attributes an unloadable binding class to that binding (`Could not resolve binding: <key> (<error>)`), instead of a separate "Could not check circular dependencies" line
- `debug:container <class>` no longer prints "Indentation shows dependency depth"; the container returns a flat list, so nothing was ever indented

### Documentation

- A Factory can declare its dependencies in its **constructor**: pillars resolve through the container, so autowiring applies to the Factory itself. This already worked and is now documented and tested
- `docs/rfc/0002` inventories every way to obtain a dependency (25 paths across 4 intents), as the basis for naming one primary path per intent in 2.0

## [1.20.0](https://github.com/gacela-project/gacela/compare/1.19.0...1.20.0) - 2026-07-25

### Added

- `AbstractFactory::make(class-string, params)` builds a domain object through the module container with autowiring, honouring `#[Inject]`/`#[Singleton]`/`#[Factory]`, with `params` overriding constructor arguments by name. Needs no bindings, so it works in a module with no Provider. Additive: `getProvidedDependency()` and hand-wired `create*()` are unchanged
- `make:module --minimal` (alias `--template=minimal`) scaffolds Facade and Factory only. Config and Provider are optional at runtime

### Changed

- `make:module`'s provider template is attribute-first, leading with a typed `#[Provides]` method. `provideModuleDependencies()` still works
- The `AbstractDependencyProvider` deprecation notice always fires now. It went through `trigger_deprecation()` and was skipped when `symfony/deprecation-contracts` was absent, which is most apps, since it is not a runtime dependency. **Expect to start seeing this notice**; migrate to `AbstractProvider`
- `GacelaConfig::addMappingInterface()` emits `E_USER_DEPRECATED`. Deprecated since 1.2.0 with no runtime signal until now. Use `addBinding()`, same arguments
- `make:module` and `make:file` throw when a generated file cannot be written. A read-only target or full disk previously printed success, exited `0`, and wrote nothing
- An unresolvable health check throws `HealthCheckNotResolvableException` instead of being skipped. A typo in `addHealthCheck(SomeCheck::class)` previously produced a report that looked healthy while the check never ran

### Removed

- `Gacela\Framework\Container\Locator::addSingleton()`: no production caller, and `Locator` is `@internal`. Seed through a `Container` plus `Locator::getInstance($container)`

### Fixed

- A module's `provideModuleDependencies()` no longer runs twice. Both provider resolvers share a normalized cache slot, so a modern Provider was registered once via `register()` and again directly, running non-idempotent provider bodies (counters, external registrations, logging) twice
- `validate:config` detects circular dependencies. It matched the exception message case-sensitively against the wrong casing, so the check never fired once and a cyclic configuration exited `0`. It now catches `CircularDependencyException` by type and prints the full `A -> B -> A` chain. **Pre-existing cycles will now fail validation (exit `1`)**, so expect CI to surface them on the first run. Other resolution failures, previously discarded silently, are reported as warnings

### Documentation

- New `docs/upgrading.md`: every 1.x deprecation with its replacement. Migrating off `AbstractDependencyProvider` also requires renaming `DependencyProvider.php` to `Provider.php`; pillars resolve by filename suffix, so renaming only the class leaves the module unresolvable
- Documented `make()` and attribute-first module DI in `docs/container-configuration.md`, and the optional Config/Provider pillars in `docs/getting-started.md`

### Internal

- Mutation testing runs in CI. `infection.json5` had required an MSI of 100 for a while but no workflow ran it, so the score had drifted below the bar

## [1.19.0](https://github.com/gacela-project/gacela/compare/1.18.0...1.19.0) - 2026-07-23

### Added

- `GacelaConfig::addBindingIf(key, value)`: register a binding only when the key is not already bound, so plugins can ship an overridable default (register-unless-overridden)

### Changed

- Confirmed PHP 8.5 support by adding it to the CI test matrix (the `>=8.1` requirement already permitted it)

### Fixed

- The opcache preload script (`resources/gacela-preload.php`) now requires PHP 8.1, matching the framework's `>=8.1`, instead of advertising and guarding an obsolete PHP 7.4 floor
- Health-check resolution no longer swallows container errors: a registered check whose container resolution throws now surfaces the real exception instead of silently falling back to a default instance and hiding the misconfiguration

### Documentation

- New `docs/production-performance.md`: a single checklist for running Gacela fast in production (file cache, `cache:warm`, opcache preload, autoloader optimisation, disabling unused event listeners, cross-request `#[Cacheable]` storage, `GACELA_CACHE_DIR`)
- Corrected the `#[Cacheable]` backtrace-cost note (measured ~0.08 µs/call on a warm hit, not 1–5 µs) and now recommend explicit `$method`/`$args` as the pattern for hot, cheap methods; added `CacheableBench` to guard the delta

## [1.18.0](https://github.com/gacela-project/gacela/compare/1.17.0...1.18.0) - 2026-07-20

### Added

- Framework lifecycle events, zero-cost when nothing listens: `GacelaBootstrapStarted`/`GacelaBootstrapFinished`, `ConfigInitialized`/`ConfigKeyRead`/`ConfigKeyNotFound`, `ServiceResolved` (once per id), `BindingRegistered`, `ProviderRegistered`, `CacheCleared`, `CacheWarmed`
- `Gacela\Framework\Testing\GacelaTestCase`: bootstrap isolation per test, config overrides, and event-backed assertions: `assertServiceResolved()`, `assertBindingRegistered()`, `recordedGacelaEventsOf()`
- Typed config accessors on `Config`/`AbstractConfig`: `getString()`, `getInt()`, `getFloat()`, `getBool()`, `getArray()`: concrete return types, `null` default means required, wrong type throws instead of coercing (int→float widening allowed); faster than `get()` + a manual cast
- Scalar contextual bindings: `$config->when(X)->needs('$paramName')->give(30)`
- `ArrayAccess` on the main container: `$container[Id::class]`, `isset`, assignment, `unset`
- `debug:module <Name>`: resolved Facade/Factory/Config/Provider, bindings, and dependency tree (`--json`, `--tree`)
- `debug:graph`: whole-app module dependency graph (`--format=text|mermaid|graphviz|json`)
- `make:module --template=service [--with-tests]`: scaffolds a module that runs out of the box: four pillars plus a `Domain` service, optionally with a `GacelaTestCase`-based facade test
- `CrossModuleViaFacadeRule`: optional `sharedNamespaces` allowlist for shared kernels

### Changed

- Bumped `gacela-project/container` to `^0.10.0`. It fixes transient resolutions sharing child instances; uncached construction gets slower (sub-microsecond per resolve), while Gacela's steady-state paths are unaffected because resolved classes are instance-cached
- Event dispatch is zero-cost when nothing listens (~20% faster warm resolves). BC note: custom `EventDispatcherInterface` implementations must add `hasListeners()`

### Fixed

- `APP_ENV` is read from a single source for both the env-suffixed config files and the merged-config cache key, so they can no longer disagree mid-process
- `ConfigLoader`'s read cache is keyed by reader and path: the same file under two readers no longer shares a cache entry
- `Gacela::resetCache()` also clears the glob cache, so config files added or removed on disk are seen by the next bootstrap in the same process
- `ProviderRegisteredEvent` no longer fires twice for a modern `AbstractProvider`
- `Config::getEventDispatcher()` returns a no-op dispatcher before bootstrap instead of throwing
- Re-bootstrapping rebuilds the event dispatcher; listeners from a previous bootstrap no longer leak
- The merged-config cache filename embeds an app-root hash: apps sharing a cache directory no longer read each other's config (old files are ignored and removed by `cache:clear`)

### Documentation

- New `docs/events.md` (dispatch model, event catalog, listener cookbook) and `docs/testing.md` (`GacelaTestCase`, `ContainerFixture`)
- Module boundaries in `docs/static-analysis.md`, config precedence in `docs/getting-started.md`, scalar bindings and the `#[Singleton]`/`#[Factory]` attributes in `docs/container-configuration.md`

## [1.17.0](https://github.com/gacela-project/gacela/compare/1.16.0...1.17.0) - 2026-07-18

### Added

- `FileCache::writeContentsAtomically(string $file, string $content): bool` atomically writes pre-rendered content with the same guarantees as `writeAtomically()`, which now wraps it

### Changed

- `AbstractFactory::singleton()` and `CacheableTrait::cached()` are generic (`@template T`), so static analysis infers the return type instead of `mixed`
- `AbstractProvider::register()` is `final`; overriding it silently disabled `#[Provides]` scanning. Use `provideModuleDependencies()`
- Class resolvers share one `Container` built once from the global bindings, instead of rebuilding one per resolver type. Reset with `Gacela::resetCache()`
- `Gacela::bootstrap()` batches file-cache writes into a single atomic write per cache file, instead of one full-file rewrite per newly discovered key
- `validate:config` no longer prints the no-op "Checking configuration paths..." placeholder section

### Fixed

- Contextual bindings (`$config->when(X)->needs(Y)->give(Z)`) apply when resolving Gacela classes (factories, configs, providers); the global binding always won before
- Health checks registered in `gacela.php` are no longer wiped when a project also ships `gacela-{APP_ENV}.php`; checks from the default and env config files accumulate
- `bin/gacela` exits 1 and writes to STDERR on every failure path (missing autoload, missing `symfony/console`, bootstrap failure, any `Throwable`), instead of exiting 0/255 with a raw stack trace
- `cache:clear` is registered in the console; it shipped in 1.13.0 but was never wired in, and it also removes the custom-services cache file (`gacela-custom-services.php`). Every cache-clear path deletes through one guarded helper that tolerates a missing file and invalidates opcache, so stale cache files no longer survive a clear
- `cache:warm` pre-warms attributes again (it called a non-existent method and swallowed the `Error`), reports module-discovery and per-class failures instead of hiding them, skips only the failing module when a facade resolution throws, and anchors its test filter on `\Test\`/`\Tests\` segments, so `TestimonialFacade` is no longer skipped
- `Config` no longer re-runs the full `init()` on every access when the merged config is empty; initialization is tracked with a flag
- Resolving a deprecated `AbstractDependencyProvider` no longer fatals without `symfony/deprecation-contracts`; the `trigger_deprecation()` call is guarded
- `bin/gacela --version` no longer drifts after releases; the version is derived at runtime from Composer metadata via `Gacela::version()`
- Calling an undocumented method through the resolver traits throws `MissingClassDefinitionException`, instead of silently resolving to the caller's first `use` import
- `debug:container --stats` shows statistics when combined with a class argument; the flag was registered but never read
- `list:modules` prints `No modules match filter "..."` when nothing matches, instead of an empty table or no output
- `getExternalService()` no longer misreports a service registered under the key `'0'` as `Available keys: none`

### Removed

- `cache:warm --parallel` and `ParallelModuleWarmer`: warming is CPU-bound, so it matched sequential warming. Use `cache:warm`
- `Gacela\Framework\Event\ClassResolver\GenericEvent`: dead code, never dispatched
- `GacelaFileCache::isEnabledFromCacheConfig()`: dead code, use `GacelaFileCache::isEnabled()`

### Documentation

- `docs/module-health-checks.md` documents `GacelaConfig::addHealthCheck()` and how checks surface in `bin/gacela doctor`
- `profile:report` help no longer claims operations are "automatically tracked"; it shows a manual `Profiler::start()`/`stop()` example
- `validate:config` help no longer implies a missing `gacela.php` is flagged; the file is optional

## [1.16.0](https://github.com/gacela-project/gacela/compare/1.15.0...1.16.0) - 2026-07-15

### Fixed

- File caches degrade gracefully in read-only environments instead of fataling the bootstrap with `Directory "..." was not created`: the class-resolver caches fall back to in-memory resolution, the merged-config auto-warm becomes a no-op, and cache writes never throw or emit raw PHP warnings. Pre-warmed cache files inside a read-only directory stay readable, so warm-at-build/run-read-only deployments keep their cache hits

### Added

- `WritableDirectory::isUsable()` answers whether a directory can hold cache files (creating it when missing), memoized per directory
- `FileCache::isPersistent()` reports whether entries reach disk or only live in memory; `FileCache::writeAtomically()` returns whether the file was written

## [1.15.0](https://github.com/gacela-project/gacela/compare/1.14.4...1.15.0) - 2026-06-05

### Added

- `GacelaConfig::addLazy()` registers lazy-loaded services that are only instantiated on first access, deferring expensive service creation to improve startup performance
- `debug:config` console command prints the effective merged configuration (optionally filtered by a key substring), plus a `Config::getAllValues()` accessor
- Class-resolution failures now list the exact class-name candidates that were tried, making naming-convention and namespace mismatches easier to diagnose

### Changed

- Merged config cache now auto-warms on miss: when file cache is enabled (`GacelaConfig::enableFileCache()`), the first bootstrap persists the merged config so subsequent bootstraps skip globbing and parsing configuration files, with no manual `cache:warm` required
- Bump `gacela-project/container` to `^0.8.1` for PHP 8.5 compatibility (removes deprecated `SplObjectStorage::attach()`/`detach()` usage)

## [1.14.4](https://github.com/gacela-project/gacela/compare/1.14.3...1.14.4) - 2026-04-20

### Added

- Windows support: `windows-latest` now part of the CI matrix

### Fixed

- Cache-dir resolution on Windows (drive-letter regex, separator handling)
- Platform-independent exception messages in `ClassResolverExceptionTrait`
- `FileCache` normalizes its directory input (trim, fold separators, preserve UNC, strip embedded Windows absolute path)

## [1.14.3](https://github.com/gacela-project/gacela/compare/1.14.2...1.14.3) - 2026-04-17

### Changed

- Upgrade to PHPStan 2.x (`phpstan/phpstan ^2.0`, `phpstan/phpstan-strict-rules ^2.0`) and Rector 2.x. Built-in Gacela PHPStan rules migrated to the 2.x rule API (`RuleErrorBuilder`, `getParents()`).

## [1.14.2](https://github.com/gacela-project/gacela/compare/1.14.1...1.14.2) - 2026-04-17

### Added

- `GacelaConfig::setAppModulePaths()` to scope module discovery to specific directories

### Fixed

- `list:modules` / `debug:modules` no longer warn on top-level dotfile PHP configs
- `validate:config` stays silent when `gacela.php` is missing (file is optional)

## [1.14.1](https://github.com/gacela-project/gacela/compare/1.14.0...1.14.1) - 2026-04-16

### Fixed

- Correct CLI version in `bin/gacela` (was still showing 1.13.0 in 1.14.0 tag)

## [1.14.0](https://github.com/gacela-project/gacela/compare/1.13.0...1.14.0) - 2026-04-16

### Added

- `CacheableTrait` in `AbstractFacade`, so facades can use `#[Cacheable]` out of the box
- `#[Inject]` constructor-parameter attribute with optional `implementation` override; `debug:dependencies` surfaces it
- `gacela/symfony-bridge`: `GacelaInjectCompilerPass` routes `#[Inject]` parameters through Gacela's container in Symfony apps
- `#[Provides('ID')]` attribute for declarative provider registration
- `FileCache<T>` typed file-backed cache with TTL, batching, and atomic writes
- `ScopedCache` decorator with dependency graph, cascading invalidation, and cycle detection
- `GacelaConfig::addHandlerRegistry()` for provider-registered dispatch tables
- `GacelaConfig::addHealthCheck()` for provider-based health checks
- `ContainerFixture` testing trait for PHPUnit container isolation

### Changed

- `CacheableTrait::cached()` infers method and args from the caller automatically
- `#[Cacheable]` storage is pluggable via `CacheStorageInterface`; supports per-method TTL overrides and `{N}` key placeholders
- `MergedConfigCache` uses `FileCache::writeAtomically()` for atomic writes

### Fixed

- `ResolvableType::fromClassName()` now uses `str_ends_with` to correctly match suffix types (e.g. `FacadeFactory` no longer misresolves as `Facade`)
- `AllAppModulesFinder::buildClassName()` handles filenames with a leading dot correctly
- `GacelaConfig::getExternalService()` throws `InvalidArgumentException` on missing key instead of silently returning null

### Performance

- `#[Cacheable]` hot path: memoized reflection, miss sentinel, scalar-key fast-path

## [1.13.0](https://github.com/gacela-project/gacela/compare/1.12.0...1.13.0) - 2026-04-15

### Added

#### Commands

- `cache:clear` removes all Gacela cache files
- `cache:warm --parallel` warms in parallel via PHP 8.1 Fibers, up to 5× faster
- `cache:warm --attributes` pre-scans and caches `#[ServiceMap]` attributes
- `debug:dependencies <class|file>` inspects a class's constructor and reports each parameter's resolvability through the container: bound → target, autowirable, has default, or unresolvable with a reason. Takes a fully qualified class name or a path to the file declaring it
- `debug:modules [filter]` walks every discovered module and inspects the constructor of each pillar, grouped by module with per-pillar resolvable/unresolvable counts (`--detail` shows every parameter). Complements `list:modules` (structural view) and `debug:dependencies` (single-class deep dive)
- `doctor` aggregates environmental and wiring health checks (cache staleness, suffix mismatches) with per-check remediation hints
- `profile:report` generates and analyzes performance reports

#### Dependency injection

- Contextual bindings via `GacelaConfig::when()`
- Service aliases via `GacelaConfig::addAlias()`
- Protected services via `GacelaConfig::addProtected()`
- `Gacela::getRequired()` and `Locator::getRequired()` throw `ServiceNotFoundException` instead of returning null

#### Facades

- `#[Cacheable]` attribute and `CacheableTrait` for automatic facade-method result caching with TTL

#### Observability

- `Profiler` for performance profiling and bottleneck detection
- Custom per-module health checks via `ModuleHealthCheckInterface`, `HealthChecker`, `HealthStatus` (OK / WARNING / ERROR / CRITICAL), and `HealthCheckReport`

### Changed

- Exception messages include did-you-mean suggestions and actionable examples via `ErrorSuggestionHelper`
- `Locator::getRequired()` passes the container's registered services and bindings to `ServiceNotFoundException`, so a typo'd service gets those suggestions
- `validate:config` reports binding-mismatch warnings with the expected interface/class, the actual type chain of the bound value, and a fix hint; interface-keyed bindings are checked too, previously skipped

### Performance

- Persist the merged file-based config to disk via `MergedConfigCache`, so bootstraps skip globbing and parsing configuration files. Produced by `cache:warm`, removed by `cache:clear`, keyed per `APP_ENV`
- `cache:warm` pre-populates the `ClassNamePhpCache` by running Gacela's resolvers against each module's Facade, so first requests skip the cold `namespaces × rules × types × class_exists` lookup in `ClassNameFinder`
- `ClassValidator` memoizes `class_exists()` results, so repeated candidate lookups reuse the autoloader probe within a request
- `DocBlockResolver` and `CacheWarmService` share `ReflectionClass` instances through a `ReflectionClassPool`
- `cache:warm` batches `AbstractPhpFileCache` writes via new `beginBatch()`/`commitBatch()` and flushes with an atomic `rename()`, so one file write replaces the previous _N modules × 4 resolvers_ full-file rewrites, and an interrupted warm cannot leave a half-written cache file

### Documentation

- Trimmed README and docs for clarity; added `docs/README.md` as a navigable index

## [1.12.0](https://github.com/gacela-project/gacela/compare/1.11.0...1.12.0) - 2025-11-09

- Renamed `DocBlockResolver` to `ServiceResolver` to better reflect its purpose
- Added `ServiceResolverAwareTrait` with caching improvements; will replace `DocBlockResolverAwareTrait`
- Introduced the `#[ServiceMap]` attribute as the preferred service binding instead of `DocBlock`
- Added `cache:warm` command to pre-resolve module classes for optimal production performance
- Added `validate:config` command to validate Gacela configuration for errors and best practices
- Added opcache preload script for 20-30% performance boost in production
- Added suppressions to `phpstan-gacela.neon` and `psalm-gacela.xml` for dynamic resolution
- Improved error messages with actionable suggestions and examples
- Added `GacelaConfig::addFactory()` to register factory services that create new instances on each resolution
- Added module-boundary PHPStan rules to `phpstan-gacela.neon`:
  - `FacadeOnlyDelegatesRule`: Facade methods must only delegate to `$this->getFactory()`, `getConfig()`, or `getProvider()`
  - `FactoryDoesNotCallFacadeRule`: Factories must not instantiate Facades or call `$this->getFacade()`
  - `CrossModuleViaFacadeRule` (opt-in): cross-module references (new/static call/const fetch) must go through a `*Facade`

## [1.11.0](https://github.com/gacela-project/gacela/compare/1.10.0...1.11.0) - 2025-10-12

- Add `phpstan-gacela.neon` for reusable PHPStan rules enforcing Gacela naming conventions (Facade, Factory, Provider, Config)
- Drop static facade magic methods; call `$facade->getFactory()` directly
- Improve PHPStan generic type support
  - Replace `@method` annotations with `@extends` for better type inference
- Improve `SetupGacela`; extract `PropertyChangeTracker` and `SetupGacelaProperties`
- Run CI tests with PHP 8.4

## [1.10.0](https://github.com/gacela-project/gacela/compare/1.9.1...1.10.0) - 2025-08-02

- Fix default cache dir
- Improve internal `AnonymousGlobal::getByKey()`
- Add internal cache on `PathFinder` and `GlobalKey`
- Added factory instance caching via new `singleton()` helper

## [1.9.1](https://github.com/gacela-project/gacela/compare/1.9.0...1.9.1) - 2024-12-12

- Better compatibility with PHP 8.4

## [1.9.0](https://github.com/gacela-project/gacela/compare/1.8.1...1.9.0) - 2024-12-01

- Compatibility with PHP 8.4
- Added `GACELA_CACHE_DIR` env variable to override where to place the cache files
- Added `RELEASE.md` docs

## [1.8.1](https://github.com/gacela-project/gacela/compare/1.8.0...1.8.1) - 2024-11-09

- Internal optimizations

## [1.8.0](https://github.com/gacela-project/gacela/compare/1.7.1...1.8.0) - 2024-08-17

- Moved `./gacela` script to `bin/` directory
- Fixed disable event listeners
- Added `Gacela::addGlobal()`
- Added `Gacela::overrideExistingResolvedClass()`
- Deprecated `AbstractDependencyProvider` in favor of `AbstractProvider`

## [1.7.1](https://github.com/gacela-project/gacela/compare/1.7.0...1.7.1) - 2024-04-16

- Keep packages sorted in composer.json
- Added `ergebnis/composer-normalize`
- Added `rector`

## [1.7.0](https://github.com/gacela-project/gacela/compare/1.6.0...1.7.0) - 2023-12-21

- Change min PHP support for `PHP>=8.1`

## [1.6.0](https://github.com/gacela-project/gacela/compare/1.5.0...1.6.0) - 2023-10-15

- Fixed combining event listeners from different `SetupGacela` objects
- Removed `ConfigNotFoundException`
- Simplify `FactoryResolverAwareTrait`
- Refactor `SetupGacela` and `FactoryResolver`

## [1.5.0](https://github.com/gacela-project/gacela/compare/1.4.0...1.5.0) - 2023-07-01

- Added command `gacela list:modules [--detailed|-d]`
- Fixed Windows support

## [1.4.0](https://github.com/gacela-project/gacela/compare/1.3.0...1.4.0) - 2023-05-20

- Added `Gacela::rootDir()`
- Added `GacelaConfig::enableFileCache()`
- Added plugins as callable
    - `GacelaConfig::addPlugin(string|callable)`
- Rename `addExtendConfig()` to `extendGacelaConfig()` in `GacelaConfig`
- Removed deprecated `withPhpConfigDefault()`

## [1.3.0](https://github.com/gacela-project/gacela/compare/1.2.0...1.3.0) - 2023-05-08

- Deleted `PluginInterface`
  - A plugin can be any class that implements `__invoke()`
- Added `GacelaConfig::addExtendConfig()`
- Remove the deprecated methods `setFileCacheEnabled()` & `setFileCacheDirectory()`

## [1.2.0](https://github.com/gacela-project/gacela/compare/1.1.1...1.2.0) - 2023-04-29

- Unify `setFileCacheEnabled` and `setFileCacheDirectory` into one single method: `setFileCache(bool $enabled, string $dir)`. Deprecated the former methods
- Rename dependency; from `resolver` to `container`.
- Moved the current `Container` logic to the decoupled `container` dependency
- Add "plugins" to run right after the `Gacela::bootstrap()`
- Deprecated `addMappingInterface()` in favor of `addBinding()`

## [1.1.1](https://github.com/gacela-project/gacela/compare/1.1.0...1.1.1) - 2023-04-19

- Deprecate `withPhpConfigDefault()` in favor of `defaultPhpConfig()`
- Extract the dependency resolver logic into a different repo `gacela-project/resolver`

## [1.1.0](https://github.com/gacela-project/gacela/compare/1.0.1...1.1.0) - 2023-03-21

- Allow using static facade methods
  - Enabled calling `::getFactory()` from a static context
- ResetInMemoryCache also from anonymous globals and factory containers

## [1.0.1](https://github.com/gacela-project/gacela/compare/1.0.0...1.0.1) - 2023-03-12

- Normalise internal events' `toString()`
- Bugfix Register only once specific events on bootstrap

## [1.0.0](https://github.com/gacela-project/gacela/compare/0.32.0...1.0.0) - 2023-01-01

- Allow extending raw arrays as services
- The Locator cannot resolve any more interface classes only because of the `Interface` suffix in their name
- Drop support for PHP 7.4

## [0.32.0](https://github.com/gacela-project/gacela/compare/0.31.0...0.32.0) - 2022-11-24

- Froze a "Container service" after its first usage with `get()`
- Added `Container::protect(service)`

## [0.31.0](https://github.com/gacela-project/gacela/compare/0.30.1...0.31.0) - 2022-11-15

- Added `Container::factory(service)`
- Added `Container::extend(id, service)`
- Added `GacelaConfig::extendService(id, service)`

## [0.30.1](https://github.com/gacela-project/gacela/compare/0.30.0...0.30.1) - 2022-11-09

- Fixed `DocBlockResolver` resolvableType
- Fixed `DocBlockResolverAwareTrait` cache

## [0.30.0](https://github.com/gacela-project/gacela/compare/0.29.0...0.30.0) - 2022-11-07

- Allow combine and override different `GacelaConfig` from project level
- Added internal events for the `ClassResolver\Cache` scope
- Fixed `PhpFileCache` bug

## [0.29.0](https://github.com/gacela-project/gacela/compare/0.28.0...0.29.0) - 2022-11-02

- Added `GacelaConfig::registerSpecificListener(event, listener)`
- Added `GacelaConfig::registerGenericListener(listener)`

## [0.28.0](https://github.com/gacela-project/gacela/compare/0.27.0...0.28.0) - 2022-10-27

- Add file cache for resolved classes
- Remove profiler, because it does the same as the file cache system under the hood

## [0.27.0](https://github.com/gacela-project/gacela/compare/0.26.0...0.27.0) - 2022-10-12

- Read autoload-dev psr-4 namespaces for gacela make commands
- Cache default resolved gacela class
- Allow optional project namespace on class name finder rules

## [0.26.0](https://github.com/gacela-project/gacela/compare/0.25.0...0.26.0) - 2022-10-01

- Added new feature: gacela file profiler (disabled by default)
- Removed gacela file cache. Instead, use InMemoryCache always
- Removed `gacela cache:clear` command

## [0.25.0](https://github.com/gacela-project/gacela/compare/0.24.0...0.25.0) - 2022-09-18

- Removed deprecated `SetupGacelaInterface` from `gacela.php`
- Allow using abstracts Factory and Config by default
- Create `gacela cache:clear` command
- Process configFn from appRootDir if exists, and it wasn't defined on bootstrap

## [0.24.0](https://github.com/gacela-project/gacela/compare/0.23.1...0.24.0) - 2022-07-23

- Change cache default directory to `.gacela/cache`
- Added project namespaces
  - `GacelaConfig::setProjectNamespaces(array)` to be able to resolve gacela classes with priorities
- Added gacela configuration for different environments
- Allow adding config key-values from GacelaConfig
  - `GacelaConfig::addAppConfigKeyValue(string, mixed)`
  - `GacelaConfig::addAppConfigKeyValues( array<string, mixed> )`
- When cache is disabled on bootstrap, Gacela won't generate `*.cache` files

## [0.23.1](https://github.com/gacela-project/gacela/compare/0.23.0...0.23.1) - 2022-06-25

- Fix `setCacheDirectory()` with nested dir levels

## [0.23.0](https://github.com/gacela-project/gacela/compare/0.22.0...0.23.0) - 2022-06-24

- Group gacela cache files inside a `cache/` directory
- Allow enabling/disabling cache files from the project config files
- Added `setCacheDirectory()` to `GacelaConfig`
- Added `vendor/bin/gacela` script
- Add `.editorconfig` & `.gitattributes` files
- Ignore `composer.lock`

## [0.22.0](https://github.com/gacela-project/gacela/compare/0.21.0...0.22.0) - 2022-06-10

- Added a (file) cache layer
  - for class-names to their resolvable-type (in a file: `.gacela-class-names.cache`)
  - for custom-services to their resolvable-class (in a file: `.custom-services.cache`)
- Delete unnecessary Backtrace for exceptions
- Rename resetCache() to setCacheEnabled() from `GacelaConfig`

## [0.21.0](https://github.com/gacela-project/gacela/compare/0.20.0...0.21.0) - 2022-05-29

- Allow only a `Closure(GacelaConfig):void` object to 2nd parameter type of `Gacela::bootstrap()`
- Add new key Gacela configuration key: `GacelaConfig::setResetCache(bool)`

## [0.20.0](https://github.com/gacela-project/gacela/compare/0.19.0...0.20.0) - 2022-05-27

- Add `GacelaConfig::withPhpConfigDefault()`
- Allow gacela anon-classes using parent methods
- Define local pattern php config default
- Add `AbstractClassResolver::resetCache()`

## [0.19.0](https://github.com/gacela-project/gacela/compare/0.18.1...0.19.0) - 2022-05-19

- Removed bin/gacela util from this repo
  - CodeGenerator moved to its own repo: `gacela-project/gacela-cli`

## [0.18.1](https://github.com/gacela-project/gacela/compare/0.18.0...0.18.1) - 2022-05-15

- Bugfix SetupGacela using proper method from parent class

## [0.18.0](https://github.com/gacela-project/gacela/compare/0.17.2...0.18.0) - 2022-05-14

- Removed default config path from config/*.php to empty
- Added allow gacela.php using a callable with GacelaConfig arg
- Moved namespace from Setup to Bootstrap (affecting SetupGacela)
  - Deprecated Setup namespace in favor of Bootstrap
- Remove deprecated `globalServices()` method
- Deprecate SetupGacelaInterface from gacela.php and `Gacela::bootstrap()`. Use callable(GacelaConfig) instead

## [0.17.2](https://github.com/gacela-project/gacela/compare/0.17.1...0.17.2) - 2022-05-02

- Ensure GLOB_BRACE constant is defined for Alpine and Solaris OS

## [0.17.1](https://github.com/gacela-project/gacela/compare/0.17.0...0.17.1) - 2022-05-02

- Removing illegal c-char from filename

## [0.17.0](https://github.com/gacela-project/gacela/compare/0.16.0...0.17.0) - 2022-04-29

- Added DocBlockResolverAwareTrait
- Deprecated FacadeResolverAwareTrait in favor of DocBlockResolverAwareTrait
- Removed deprecated setup as array in `Gacela::bootstrap()`
- Allow overriding Gacela resolvable Facade type

## [0.16.0](https://github.com/gacela-project/gacela/compare/0.15.0...0.16.0) - 2022-04-14

- Combine gacela file and bootstrap setup
- Rename the concept of GlobalServices to ExternalServices
- Make the Facade accessible from module-internal sub-folders
- Allow to return an instance of SetupGacela on gacela.php

## [0.15.0](https://github.com/gacela-project/gacela/compare/0.14.0...0.15.0) - 2022-03-26

- Updated ClassInfo improve performance adding cache
- Renamed GlobalServices to Setup
- Added SetupGacela to replace AbstractConfigGacela
- Added support for dark mode logo

## [0.14.0](https://github.com/gacela-project/gacela/compare/0.13.0...0.14.0) - 2022-03-14

- Updated from protected to public the `getAppRootDir()` from `AbstractConfig`
- Updated `AbstractConfigGacela` to use builders instead of returning arrays

## [0.13.0](https://github.com/gacela-project/gacela/compare/0.12.0...0.13.0) - 2022-03-01

- Added allow defining a config reader as class-string too
- Moved the "config readers" next to their config item itself
  - Performance improvement specially when using different config readers in the same project
- Added OverrideResolvableTypes feature
  - Allow overriding Gacela resolvable types (Factory, Config, DependencyProvider)
- Removed deprecated methods `getApplicationRootDir()` & `setApplicationRootDir()` from Config
  - Use `getAppRootDir()` & `setAppRootDir()` instead
- Deprecated and removed `CustomService` feature. Use `MappingInterfaces` feature instead
  - Why? Too much magic

## [0.12.0](https://github.com/gacela-project/gacela/compare/0.11.0...0.12.0) - 2022-02-13

- Added `getAppRootDir()` to AbstractConfig
- Added `APP_ENV` environment key, to define different config files on different environments
- Added `'config-readers'` key in the globalServices and `gacela.php`
- Added `'custom-services-location'` key in the globalServices and `gacela.php`
  - Define namespaces (relative to a module) where Gacela should check for custom services that will be auto-resolved
- Deprecated `getApplicationRootDir()` from Config. Use `getAppRootDir()` instead
- Removed `EnvConfigReader` from `gacela-project/gacela`
  - If you want to read `.env` values, you should require `gacela-project/gacela-env-config-reader`

## [0.11.0](https://github.com/gacela-project/gacela/compare/0.10.0...0.11.0) - 2022-01-18

- Deleted deprecated array config in `gacela.php`
- Allow `null` as default config value
- The globalServices are passed into `mappingInterfaces()` and not as constructor argument

## [0.10.0](https://github.com/gacela-project/gacela/compare/0.9.0...0.10.0) - 2021-10-04

- Allow setup custom config from `Gacela::bootstrap()` directly

## [0.9.0](https://github.com/gacela-project/gacela/compare/0.8.0...0.9.0) - 2021-08-27

- Allow return JsonSerializable objects in PHP config files

## [0.8.0](https://github.com/gacela-project/gacela/compare/0.7.0...0.8.0) - 2021-08-16

- Updated `gacela.php` config file:
  - returning a simple array has been deprecated
  - an anonymous function that creates an anonymous class that extends from AbstractConfigGacela should be used
- Remove deprecated `gacela.json` config file

## [0.7.0](https://github.com/gacela-project/gacela/compare/0.6.0...0.7.0) - 2021-08-07

- Improve the flexibility from the ConfigReaders
- Deprecated `gacela.json` config file, in favor of `gacela.php`
- Added 'mapping-interfaces' key to `gacela.php`
- Added autowiring for Factory dependencies

## [0.6.0](https://github.com/gacela-project/gacela/compare/0.5.0...0.6.0) - 2021-07-27

- Added `AbstractClassResolver::overrideExistingResolvedClass()`
- Locator uses `AbstractClassResolver::getGlobalInstance()` before creating a new instance
- Unify the cacheKey using `GlobalKey`

## [0.5.0](https://github.com/gacela-project/gacela/compare/0.4.0...0.5.0) - 2021-07-19

- `Config::setConfigReaders()` create a new config instance singleton
- Added `AbstractClassResolver::addAnonymousGlobal()` you can now use anonymous classes
- Added matrix for the GitHub CI for diff PHP versions (7.4,8.0), and diff OS (mac,linux,windows)

## [0.4.0](https://github.com/gacela-project/gacela/compare/0.3.0...0.4.0) - 2021-07-10

- Allow multiple (and different) config files defined in `gacela.json`
- Make extensible the Config Readers

## [0.3.0](https://github.com/gacela-project/gacela/compare/0.2.0...0.3.0) - 2021-07-04

- Allow using config php and env files defined in `gacela.json`
- Use long name by default in the generator code commands. Optional short names

## [0.2.0](https://github.com/gacela-project/gacela/compare/0.1.0...0.2.0) - 2021-04-27

- Added CodeGenerator
- Refactoring Config reading all php files from config directory

## [0.1.0](https://github.com/gacela-project/gacela/compare/690484441389a2d3bd921ab7f278c6d945f50cac...0.1.0) - 2021-04-02

- Added Facade, Factory, Config and DependencyProvider basic functionality
- Provide documentation for each of these concepts with examples
