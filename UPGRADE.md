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
