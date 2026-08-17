# Upgrading

## Why upgrade to 2.0

The whole runtime change is two lines, and almost everything below follows from the second one:

```diff
-"php": ">=8.1",  "gacela-project/container": "^0.10.0"
+"php": ">=8.3",  "gacela-project/container": "^2.0.2"
```

**Your container stops being a 0.x.** This is the reason, and it is not available to you on 1.x at any version — `Gacela\Container\Container` became `final` at 1.0, so crossing that line is breaking by construction. What it buys:

- `#[Lazy]` — defer construction until first use, for an expensive service a request may never reach
- `#[Inject]` on properties, for classes whose constructor is not yours to change
- `has()` answering the PSR-11 question ("will `get()` resolve this?") rather than "was this registered?"
- Container exceptions naming the problem, where 0.x emitted raw PHP errors from inside the container — an unwritable cache path, an unreadable cache file, a `get()` on an abstract class
- A stability promise. 0.x had none

**Static analysis that fails instead of shrugging.** 1.21 let you *write* `#[ServiceMap]`. 2.0 makes omitting it an error, because the PHPStan suppression Gacela used to ship is gone — and adds a Psalm plugin doing the same job. The gap is wider than it sounds: a suppressed accessor evaluated to `mixed`, which switched off checking of everything reached *through* it. On 1.x, `$this->getFacade()->typoMethod()` passes.

**Three fixed state leaks that will never reach 1.x.** 1.21.0 is the final 1.x release, so these are upgrade-only. The sharpest: `ClassValidator` memoized the *negative* `class_exists()` answer and never cleared it, so a class that became loadable stayed "missing" for the life of the process. That bites long-running workers, code generation, and `cache:warm`.

**One container, with module scopes.** This was the original 2.0 headline, sequenced out to 2.1 to keep it reviewable, and landed here once it could ship on its own. `AbstractFactory` used to build one container per Factory class, each walking the whole of `gacela.php`; each module now gets a scope of one shared app container, carrying only its own Provider. Module isolation is unchanged — registration is not copied and a miss falls through, so a module still cannot see a sibling's provider keys.

### What this release is not

- **A performance release on container construction, not on resolution.** The three perf spikes on the roadmap were measured and came out sub-millisecond, so they were closed rather than shipped, and writing compiled constructor plans to disk measured a net loss. What did land is structural, and it is worth least to the applications that need it least: an application with little app-wide configuration sees almost none of the construction saving, and pays ~2.5% on warm class resolution for the scope fall-through
- **Not a Symfony unblock.** Symfony is a dev dependency of Gacela, never a runtime one. A Symfony 7 or 8 application already works on 1.21

**So who should wait?** If you are on PHP 8.3 already, not running long-lived workers, and not using Psalm, your reason to move *today* is thin.

But be clear about what waiting means: **1.21.0 is the final 1.x release.** There will be no 1.22. Fixes land on 2.0 and later only — including the two cache-reset bugs fixed during this release, which affect long-running workers and are not coming to 1.x. Staying is a supported position, not a maintained one.

Set against that, the cost of moving is three mechanical renames, and `doctor` catches the only one that fails silently. It is a cheap major to take deliberately now rather than under pressure later.

## 1.21 → 2.0

Ordered by how likely each change is to affect you: the PHP floor, then three mechanical renames a project of any size will hit, then the static-analysis change, then four that only bite specific shapes of code.

If you are not ready, **1.21 is feature-complete** and deliberately ships the tooling for this migration — the typed pillar accessors, `FacadeInterfaceInSyncRule`, and `doctor`'s filename check all landed in 1.x so you can do the work *before* the major, not as a reward for taking it. It is also the last 1.x release, so treat it as a staging post rather than a destination.

### Before you start

```bash
composer require gacela-project/gacela:^1.21   # if you are not already there
vendor/bin/gacela doctor                        # names the files 2.0 will stop resolving
vendor/bin/gacela cache:clear                   # deprecation notices are memoized per caller
```

Run your test suite on 1.21 with deprecations visible — `error_reporting(E_ALL)` if your test bootstrap narrows it. Every `E_USER_DEPRECATED` from Gacela is a thing 2.0 either changed or will remove in 3.0.

The three removals have been deprecated for a while, and each still runs on 1.21:

| Removed in 2.0 | Replacement | Deprecated since |
|---|---|---|
| `AbstractDependencyProvider` | `AbstractProvider` | 1.8.0 |
| `GacelaConfig::addMappingInterface()` | `GacelaConfig::addBinding()` | 1.2.0 |
| `DocBlockResolverAwareTrait` | `ServiceResolverAwareTrait` | 1.12.0 |

Two of the three emit a runtime notice. `DocBlockResolverAwareTrait` cannot — PHP gives no hook for "a trait was used" — so grep for that one directly:

```bash
grep -rn "DocBlockResolverAwareTrait" src/
```

---

### 1. PHP 8.3

The floor is `>=8.3` (was `>=8.1`). PHP 8.1 reached end of life in December 2025 and 8.2's security window closes in December 2026, so a 2.0 pinned to either would ship already needing another bump.

Nothing to change in your code — but if you cannot move off 8.1 or 8.2, 1.21 is where you stop, and it will not receive further releases. Both of those PHP versions are themselves past or near end of life, so this is a reason to plan the runtime upgrade, not a place to settle.

### 2. `*DependencyProvider` → `*Provider`

`AbstractDependencyProvider` is gone. It was a deprecated alias with an identical API.

```diff
-final class MyModuleDependencyProvider extends AbstractDependencyProvider
+final class MyModuleProvider extends AbstractProvider
```

**Three things change, and the third is the one people miss: the file.**

```diff
-src/MyModule/MyModuleDependencyProvider.php
+src/MyModule/MyModuleProvider.php
```

Gacela resolves pillars by *filename* suffix, so a class renamed without its file stops resolving with nothing pointing at the cause. The internal `DependencyProviderResolver` and the dual-resolver path in `AbstractFactory` are gone, so `<Module>DependencyProvider` is no longer auto-resolved at all — module resolution goes through a single `ProviderResolver` looking for `<Module>Provider`.

This fails quietly rather than loudly: the provider simply never runs, and you find out when a dependency it registered comes back missing. `vendor/bin/gacela doctor` names every mismatched file for you — run it on 1.21, before you upgrade.

`provideModuleDependencies()` keeps working on `AbstractProvider`, unchanged. If you would rather register by attribute, `#[Provides]` is the attribute-first path:

```php
final class MyModuleProvider extends AbstractProvider
{
    #[Provides]
    public function someService(): SomeService
    {
        return new SomeService();
    }
}
```

**A side effect worth knowing:** on 1.x a module's `provideModuleDependencies()` could run **twice**, because the two provider resolvers shared a normalized cache slot. With the dual path gone it runs once. If your provider body is not idempotent — counters, external registrations, logging — that changes its behaviour for the better, but it does change it.

### 3. `addMappingInterface()` → `addBinding()`

```diff
-$config->addMappingInterface(MyInterface::class, MyImplementation::class);
+$config->addBinding(MyInterface::class, MyImplementation::class);
```

Same arguments, same behaviour.

### 4. `DocBlockResolverAwareTrait` → `ServiceResolverAwareTrait`

```diff
-use Gacela\Framework\DocBlockResolverAwareTrait;
+use Gacela\Framework\ServiceResolverAwareTrait;
```

On 1.x `DocBlockResolverAwareTrait` was nothing but `use ServiceResolverAwareTrait;`, so swapping it changes no behaviour. 2.0 drops the alias.

Note the guts are still `DocBlock*`-named internally. That is deliberate and cosmetic — `DocBlockParser` genuinely parses docblocks, so a blanket rename would make it *less* accurate.

### 5. Declare your pillar accessors, or PHPStan will report them

1.x shipped an `ignoreErrors` entry in `phpstan-gacela.neon` silencing `Call to an undefined method ...::getFacade()`. **That suppression is gone.**

A class reaching a pillar through `ServiceResolverAwareTrait` must now declare it:

```php
use Gacela\Framework\ServiceResolver\ServiceMap;

#[ServiceMap(method: 'getFacade', className: MyModuleFacade::class)]
final class MyController
{
    use ServiceResolverAwareTrait;
}
```

A `@method` docblock also works — PHPStan reads those natively.

This is worth doing regardless of the error. A suppressed call was never a *typed* call: it evaluated to `mixed`, which switched off checking of everything reached through the accessor. `$this->getFacade()->typoMethod()` passed.

**If you use Psalm**, register the new plugin in `psalm.xml` — it cannot be delivered through the existing XInclude, because XInclude replaces a single element and `<plugins>` lives elsewhere in your config:

```xml
<plugins>
    <pluginClass class="Gacela\Psalm\Plugin"/>
</plugins>
```

**Keep the `@method` docblock alongside the attribute** if you rely on IDE completion. Both being present is supported and recommended.

### 6. Only if you type-hinted the concrete container

`gacela-project/container` moved to `^2.0.2` (was `^0.10.0`), and `Gacela\Container\Container` is `final` from 1.0. `Gacela\Framework\Container\Container` therefore decorates it by composition instead of extending it — which is what its docblock always claimed it did.

It still implements `ContainerInterface`. **This only affects code that passed a Gacela container somewhere the concrete `Gacela\Container\Container` was type-hinted:**

```diff
-function takesContainer(\Gacela\Container\Container $container): void
+function takesContainer(\Gacela\Container\ContainerInterface $container): void
```

Provider closures are unchanged — `static fn (Container $c) => $c->getLocator()->get(...)` still receives the Gacela container, not the inner one.

### 7. Only if you override those class constants

Class constants on `AbstractSetupGacela` and `ConfigInterface` now declare types (PHP 8.3 typed class constants). A subclass redeclaring one with a different type is a compile-time error instead of silently changing the shape a builder reads.

### 8. Only if you pin Symfony

`symfony/*` is now `^7.0 || ^8.0` (was `^6.4`). Gacela no longer decides your Symfony major for you. If you were on Symfony 6, move to 7 or 8.

### 9. Only if you read `getContainerStats()`

`ConsoleFacade::getContainerStats()` and `ConsoleFactory::getContainerStats()` return a `ContainerStats` object instead of an array:

```diff
-$stats = $facade->getContainerStats();
-echo $stats['registered_services'], ' services, ', $stats['memory_usage'];
+$stats = $facade->getContainerStats();
+echo $stats->registeredServices, ' services, ', $stats->processMemoryFormatted();
```

`ContainerStats` is `final readonly` and does not implement `ArrayAccess`, so array indexing fatals rather than degrading. The keys were snake_case; the properties are camelCase: `registeredServices`, `frozenServices`, `factoryServices`, `bindings`, `cachedDependencies`, `processMemoryBytes` — plus `processMemoryFormatted()` for the human-readable string the old `memory_usage` key held.

**That last one is named for what it measures.** It is `memory_get_usage(true)` for the whole PHP process, not this container's footprint — two containers in the same process report the same number, and it moves when anything anywhere allocates. It was `memoryUsage*` until the container renamed it, because the old name invited exactly the wrong reading.

That is the point of the change. The array came straight from the container package, which explicitly excludes its shape from backward compatibility ("do not build logic on it"), so indexing it was never safe — this makes a rename fail at analysis time instead of in a user's terminal.

Not to be confused with `Container::getStats()`, a different method on the container itself, which still returns the array and is unchanged for all of 1.x.

### 10. Only if you listen to `CacheWarmedEvent`

`failedCount()` now means what it says. In 1.x it received the **skipped** count, so a listener alerting on it fired on a healthy `cache:warm` — any module with a pillar class that is simply not there. The two are separate now:

```php
$config->registerSpecificListener(CacheWarmedEvent::class, static function (CacheWarmedEvent $event): void {
    // 1.x: also true for a healthy warm. 2.0: only for a real failure.
    if ($event->failedCount() > 0) {
        alert($event->failedCount() . ' pillars failed to resolve');
    }

    $event->skippedCount(); // new: the pillars a module does not have
});
```

No signature to change — `skippedCount` is a third constructor argument defaulting to `0`. If you were treating `failedCount()` as "skipped", read `skippedCount()` instead. The `cache:warm` summary gains a matching `Classes failed:` line, so anything scraping that output sees one extra row.

---

## 2.3 → 2.4

Seven changes: `registerSpecificListener()`, if you ever pointed one at a parent class or an interface; `setEventDispatcher()`, if you supply your own dispatcher; `#[Cacheable]`, if you set a custom `key:` template; the two opt-in cross-module rules, if you have them enabled; `addAppConfig()` with a wildcard, if a file in that directory is named after another one with a `-suffix`; `doctor`, if you run it with `--strict` and register a listener against something that is not an event type; and the container the four pillars are built from, which now applies the whole of `gacela.php`.

### 1. A specific listener matches by inheritance

`registerSpecificListener()` compared `$event::class` exactly, so a target that was an interface or an abstract parent matched nothing and the listener never ran. It now matches the class named **and everything that extends or implements it**.

Nothing to do if every target you registered is a concrete event class — that keeps matching exactly one event. The one thing to look for is a listener that was registered against a parent class or an interface and has been silently inert: it starts running, for every event below that type. `vendor/bin/gacela doctor` used to report those targets as unfireable and no longer does, so if the report is where you would have seen it, look for it in the code instead.

The upside is that this is now the cheap way to observe a family. `registerSpecificListener(AbstractGacelaClassResolverEvent::class, …)` covers all four resolver events and leaves every other dispatch site allocating nothing, where the generic-listener-plus-`instanceof` pattern the docs used to teach allocates every event in the framework to throw most of them away.

### 2. A supplied dispatcher composes with your listeners instead of replacing them

Only if you call `setEventDispatcher()`. Nothing to change for the majority of that group, and for one shape this is the fix rather than the change.

Before, a supplied dispatcher and a registered listener could not coexist, and which one you lost depended on where you wrote them:

- **Registered in `gacela.php`, dispatcher supplied in the closure** — your dispatcher was thrown away. The merge built a `ConfigurableEventDispatcher` to hold the listeners and installed it over the top, and since that class is `final` yours could never be the one kept. Your bus received the two events dispatched before `gacela.php` was read and then nothing. There is nothing to do here: this was a bug, and it is fixed
- **Both in the same closure** — your listeners were the ones lost. The dispatcher was written into the slot the built one is memoized in, so the listeners were never reached. **This is the behaviour change**: those listeners now run

Both now compose. The configured listeners run first, in registration order, and then the event is offered to your dispatcher — which receives it only if its own `hasListeners()` says yes, so declining an event class still means you are not told about it. With no listeners registered anywhere, your dispatcher *is* the dispatcher and nothing is composed.

So the one thing to look for is a listener registered in the same closure as a `setEventDispatcher()` call, which has been silently inert and starts running. If you registered it expecting your dispatcher to handle it instead, delete the registration; if it was dead code you had forgotten, delete it for that reason. `vendor/bin/gacela debug:events` lists what is registered and now says when a custom dispatcher is installed beside it.

`disableEventListeners()` is unaffected: a supplied dispatcher still takes precedence over it, and with the switch off the configured listeners do not run at all.

### 3. A custom cache key is scoped to its class and method

A `key:` template used to be the whole stored key. Two classes writing the same template — `key: 'user:{0}'` in two facades — therefore shared one entry, and the second to ask was served the first one's data. It also put those entries out of reach of `clearMethodCacheFor()`, which deletes by a `Class::method::` prefix the template never carried.

The stored key is now `Class::method::` followed by the interpolated template. Two consequences:

- **A persistent backend takes one cold pass after upgrading.** Entries written under the old shape are never read again; they expire on their own TTL. If your backend has no eviction and the volume matters, `clearMethodCache()` once after deploying drops them
- **A template can no longer share one entry across classes.** If two facades deliberately cached the same value under the same template, each now caches its own. That was a coincidence of spelling rather than a feature; if sharing is what you want, cache in the one place that owns the value and call it from both

`clearMethodCacheFor()` now reaches custom-keyed entries like any other, so the "invalidate through the storage backend directly" workaround the docs used to describe is no longer needed.

### 4. The cross-module rules report less than before

Only if `CrossModuleViaFacadeRule` / `CrossModuleMethodCallRule` (PHPStan) or `<crossModule>` (Psalm) is enabled. Nothing to do, and nothing new fails — but a rule that reports *less* is still a behaviour change, and it is worth knowing which findings went away rather than wondering.

Both rules now leave a module's public API alone: a class carrying the new `#[PublicApi]`, and — this is the part that changes an existing setup — a class under a sub-namespace the module publishes by convention. The convention defaults to the segment names `Shared`, `Transfer`, `Dto` and `Event`, matched whole at any depth under each module. So `App\Billing\Shared\Invoice`, `App\Billing\Transfer\Order` and `App\Billing\Domain\Dto\Money` stop being reported, and `App\Billing\EventHandler\Projection` does not — a prefix is not a segment.

If your modules use one of those names for something that is *not* public, turn the convention off and export class by class instead:

```neon
# phpstan.neon — on both rules
arguments:
    publicApiSegments: []
```

```xml
<!-- psalm.xml, inside <crossModule> -->
<publicApiSegment/>
```

An empty `<publicApiSegment/>` rather than no element at all: leaving it out means "use the default", which is what makes the default arrive without anybody writing it.

Going the other way, entries in `ignoreReceivers` / `<ignoreReceiver>` naming a class **you own** are now better written as `#[PublicApi]` on the class — one declaration, in the module that owns it, read by both analysers. The lists stay, and remain the right answer for third-party types you cannot annotate.

One thing this does *not* touch: `DeclaredModuleDependencyRule` and `debug:graph --check` ignore `#[PublicApi]` entirely. A dependency your rules file forbids is still forbidden, whatever the class at the end of it is annotated with.

If you construct `Gacela\StaticAnalysis\ModuleBoundary` directly — unusual; the rules are the public surface — it takes a fourth constructor argument, the list of published segments. Pass `[]` for the previous behaviour.

### 5. A wildcard config path no longer reads your environment files into the base layer

Only if you pass a wildcard to `addAppConfig()` — `config/*.php`, which is what `bin/gacela init` scaffolds.

That pattern was globbed literally, so it matched the environment files Gacela names itself:

```
config/app.php
config/app-prod.php        # matched by config/*.php too
config/app-prod-eu.php     # and this one
```

All three were read into the **base** layer, on every run, before the `APP_ENV`-and-dimensions chain that selects one was applied on top. It came out right by accident of `glob()` ordering — `-` sorts before `.`, so `app.php` was merged last and won — but only for a key the base file also set. A key that existed **only** in an environment file had nothing to overwrite it, so a developer read the production value with nothing said.

The base layer now excludes any match named after another match plus one or more trailing `-<segment>` parts: `app-prod-eu.php` → `app-prod` → `app`, which is a file the same pattern matched, so it is a layer of `app.php` rather than part of the base. Anchored on another matched file, so a directory holding only `config/app-prod.php` still loads it — the exclusion can never empty a base layer.

**Two things to look for:**

- **A key you only ever set in an environment file.** It is no longer readable outside that environment. That is the fix, and it is the whole point — but if some code path outside production was quietly relying on the value, it now gets "key not found". Give the key a base value, or a `default()` in `declareConfigSchema()`
- **A file that is not an environment layer at all.** `config/app-extra.php` beside `config/app.php` matches the naming scheme whatever it was written for, so it is excluded too and read only when the chain resolves to `extra`. The rule is about names; it cannot know intent

`vendor/bin/gacela doctor` reports the second one. Its **config environment layers** check names every file excluded this way, the base file it is taken to refine, and the values that put it in play:

```
✓ config environment layers
    /app/config/app-prod.php matches a base config path but is excluded from it:
    an environment layer of /app/config/app.php, read only when APP_ENV=prod
```

If a file in that list is not an environment layer, rename it so it is not named after another one, or give it its own `addAppConfig()` path. The check is a pass, not a warning: for every project that uses `APP_ENV` or a dimension, this is what correct looks like.

**Run `cache:clear` after deploying this** if you have `enableFileCache()` on. The merged-config cache is a file of *values*, invalidated by the mtimes of the files that produced them — and upgrading Gacela touches none of those, so a warm cache goes on serving the old merge and this change appears not to have happened.
### 6. `doctor` reports a listener target that no event can be

Only if you register specific listeners, and only a new **warning** — which fails the command under `--strict`, so a CI job running `vendor/bin/gacela doctor --strict` is where you would meet it.

The check used to accept any target that named a real class or interface. It now also asks whether an event could ever match it, against the framework's events *and* your own, and reports a target that is neither an event type nor something a known event extends or implements:

```
⚠ event listeners
  App\Billing\InvoiceIssued is not an event type, and no known event extends or
  implements it -- so nothing can ever match it
```

Almost always this is the mistake it looks like: a class of your own that is missing `implements GacelaEventInterface`. The registration was accepted, the dispatcher could never match it, and nothing said so before. Adding the interface fixes both the report and the listener.

The other way to meet it is an event of yours that Gacela does not know about, because the class sits outside the paths discovery walks. `vendor/bin/gacela debug:events` lists the events it found — if yours is missing from that listing, widen `appModulePaths` or `setProjectNamespaces()` rather than changing the listener. Nothing is reported when the catalog is empty, which is what keeps a scoped run from calling every target dead.

A target that exists and *can* match is still left alone whether or not this deployment dispatches it: a listener waiting for an event nobody raises is waiting, not broken.

Two additions come with it, neither of which can break anything: `debug:events` lists your own events beside the framework's with a `source` field in `--json`, and `setEventDispatcher()` now also accepts a PSR-14 dispatcher. If you already wrote an adapter around your bus to satisfy Gacela's interface, keep it — it answers `hasListeners()` for itself, which the built-in wrapper cannot.

### 7. A pillar's constructor sees the whole configuration

The class resolver builds your Facade, Factory, Config and Provider from a container of its own. That container was seeded with `addBinding()` and `when()` and nothing else, so everything else in `gacela.php` — `loadDefinitions()`, `extendService()`, `afterResolving()`, the id-keyed verbs, tags, handler registries, plugin stacks — reached every container *except* the one that builds the four classes a module is made of. It is now configured exactly like the application container.

Mostly this only turns failures into successes: a pillar constructor asking for an interface a definitions file declared threw `DependencyNotFoundException` while `Gacela::container()->get()` returned it happily, and now builds. Three things change for code that was already working:

- **A definition now beats an `addBinding()` for a pillar constructor**, as it already did everywhere else. Definitions apply last so the data layer can override the imperative one; the pillar container never applied them, so it kept the `addBinding()` value. If you declare the same id both ways *and* a pillar takes it in its constructor, that pillar gets the definition's implementation now
- **`afterResolving()` fires for pillars.** A hook registered against a Facade, Factory, Config or Provider class — or against an interface one of them implements — did not run when the class resolver built it, and does now. A hook that appends to a collection or increments a counter runs once more per pillar than it used to
- **A Provider reading an id off the resolver's container gets the configured answer.** `addFactory()`, `addProtected()`, `addAlias()`, `addLazy()`, the tags, the handler registries, the plugin stacks and the `extendService()` decorations are all present there now, where before every one of them was missing. None of them fills a *constructor parameter* — autowiring matches by type and those register by id, in every container — so nothing that resolved before resolves differently

`BindingRegisteredEvent` is unchanged: the pillar container applies the same declarations silently, because the event describes the configuration and the configuration is still walked once. Counts, `assertBindingRegistered()` and `debug:events` see exactly what they saw before.

**One thing can turn a green build red.** `debug:modules --check` used to read the bindings of `Gacela::container()`; it now reads the container that does the building. Where something reaches the application container and not the configuration — a plugin calling `bind()` at runtime is the plain case — a pillar depending on it was reported as fine and is now reported as a fault. That report is correct: the class resolver cannot build that pillar. Move the registration into `gacela.php`, where the class resolver can see it.

---

## 2.2 → 2.3

Two changes, both in the scaffolder, both affecting scripts rather than application code. Nothing to change if you only ever run `make:module` and `make:file` by hand, on names that do not exist yet.

### 1. Generating over existing files is refused

`make:module` and `make:file` used to replace whatever was already there and report `created successfully` for each file. There was no prompt and no flag: the only record that hand-written code had been there was the file just written over it.

Both now check **every** target before writing the first, so a run that would replace something writes nothing at all:

```
$ vendor/bin/gacela make:module App/Ledger
src/Ledger/LedgerFacade.php, src/Ledger/LedgerFactory.php, src/Ledger/LedgerProvider.php already exist.
Nothing was written. Pass --force to replace them.
```

It exits `1`. **If a script of yours regenerates a module in place, it now fails there** — add `--force` if replacing really is the intent.

The all-or-nothing part is worth knowing when only some files are missing. In the run above `LedgerConfig.php` did not exist, and it was still not written, because three of its siblings were in the way. To fill one gap without touching the rest, ask for that file:

```bash
vendor/bin/gacela make:file App/Ledger Config
```

`make:module --force` is the other option and a blunter one: it rewrites all four pillars, including the three you wanted to keep.

### 2. A kind Gacela does not have is refused, not approximated

`make:file` used to answer an unrecognised kind with the closest pillar. `Repository` produced a `Factory`; `Controller`, `Service` and `Middleware` produced a `Provider`; `Migration` produced a `Factory` — each reported as created, under a name you did not ask for.

```
$ vendor/bin/gacela make:file App/Wallet Repository
"Repository" is not one of the filenames make:file can generate: Facade, Factory, Config, Provider.
Declare it with addResolvableType('Repository') in gacela.php to generate it as a kind of its own.
```

Also exits `1`. Abbreviations still work — matching is the letters you typed, in order, somewhere in the kind's name — so `cade`, `tory`, `fig` and `de-pr` reach their pillars as before. What no longer resolves is a word that is not an abbreviation of any of them.

If you were relying on one of those to stand in for a real kind, `addResolvableType()` makes it real, with its own suffix, resolver and stub — see [Resolve a kind of my own](docs/getting-a-dependency.md#resolve-a-kind-of-my-own).

---

## Deprecated in 2.0, removed in 3.0

Not blocking this upgrade, but the notices start now.

**Resolving a pillar from a `@method` docblock, or by scanning the caller's `use` statements**, raises `E_USER_DEPRECATED`. Declare it with `#[ServiceMap(method: ..., className: ...)]` — the attribute is checked first, so adding it silences the notice. Each notice names the class it resolved, spelled `\Fully\Qualified\Name::class`, so the line it suggests pastes into any namespace unchanged.

The generic form counts too. `@extends AbstractFacade<MyFactory>` names the factory by its short name, which is resolved through the file's `use` statements — the second deprecated strategy. Typing a pillar generically is still worth doing for the analysers; it is not a substitute for the attribute.

**Neither analyser finds these on its own.** PHPStan reads `@method` and `@extends` natively, so a class carrying either is a class it considers correct — with or without the attribute. A green analysis run says nothing about whether you are ready for 3.0.

Gacela ships a rule that does, off by default because what it reports is not wrong on 2.x — turning it on is the decision to start this migration:

```neon
# phpstan.neon
services:
    -
        class: Gacela\PHPStan\Rules\ServiceMapMissingRule
        tags: [phpstan.rules.rule]
```

```xml
<!-- psalm.xml -->
<pluginClass class="Gacela\Psalm\Plugin">
    <serviceMapMissing/>
</pluginClass>
```

Each finding names the attribute to paste. It covers `@method` accessors on classes using `ServiceResolverAwareTrait` — not the `@extends` generic form below, which `FactoryResolver` resolves by naming convention rather than from the docblock. See [Static analysis](docs/static-analysis.md#finding-what-30-removes).

The notice fires on a **cold resolve only**, because the answer is memoized per caller-and-method. To surface every occurrence:

```bash
vendor/bin/gacela cache:clear
```

or develop with the file cache off. Even then you only see the accessors your run actually calls, so the list is as complete as the code paths you exercise — a suite that never touches a module never reports it.

---

## Not changing

- **`Container::getStats()`** — the method on the container itself — still returns its array and is unchanged for all of 1.x. Gacela's own `debug:container` moved to the typed `stats(): ContainerStats`, because upstream excludes the array's shape from its backward-compatibility promise; that is good advice for your code too, but nothing is forcing it. Do not confuse this with `ConsoleFacade::getContainerStats()`, which **did** change — see step 9 above
- **Config, facades, factories, `#[Provides]`, contextual bindings** — untouched
