# Upgrading

## Why upgrade to 2.0

The whole runtime change is two lines, and almost everything below follows from the second one:

```diff
-"php": ">=8.1",  "gacela-project/container": "^0.10.0"
+"php": ">=8.3",  "gacela-project/container": "^1.4.0"
```

**Your container stops being a 0.x.** This is the reason, and it is not available to you on 1.x at any version — `Gacela\Container\Container` became `final` at 1.0, so crossing that line is breaking by construction. What it buys:

- `#[Lazy]` — defer construction until first use, for an expensive service a request may never reach
- `#[Inject]` on properties, for classes whose constructor is not yours to change
- `has()` answering the PSR-11 question ("will `get()` resolve this?") rather than "was this registered?"
- Container exceptions naming the problem, where 0.x emitted raw PHP errors from inside the container — an unwritable cache path, an unreadable cache file, a `get()` on an abstract class
- A stability promise. 0.x had none

**Static analysis that fails instead of shrugging.** 1.21 let you *write* `#[ServiceMap]`. 2.0 makes omitting it an error, because the PHPStan suppression Gacela used to ship is gone — and adds a Psalm plugin doing the same job. The gap is wider than it sounds: a suppressed accessor evaluated to `mixed`, which switched off checking of everything reached *through* it. On 1.x, `$this->getFacade()->typoMethod()` passes.

**Three fixed state leaks that will never reach 1.x.** 1.21.0 is the final 1.x release, so these are upgrade-only. The sharpest: `ClassValidator` memoized the *negative* `class_exists()` answer and never cleared it, so a class that became loadable stayed "missing" for the life of the process. That bites long-running workers, code generation, and `cache:warm`.

### What this release is not

- **Barely a performance release.** The three perf spikes on the roadmap were measured and came out sub-millisecond, so they were closed rather than shipped, and writing compiled constructor plans to disk measured a net loss. One exception is real but narrow: module containers now share a constructor-plan cache, worth ~37-41% of resolution time and peak memory to a request that touches many modules. A handful of modules collects little
- **Not the "one container" release.** That was the original headline and it moved to 2.1 — not blocked any more, since container 1.3 shipped the `createScope()` primitive and [container#106](https://github.com/gacela-project/container/issues/106) is closed, but deliberately sequenced after the cache-regression suite this release builds. 2.0 is the foundation that makes it possible, not the thing itself
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

`gacela-project/container` moved to `^1.4.0` (was `^0.10.0`), and `Gacela\Container\Container` is `final` from 1.0. `Gacela\Framework\Container\Container` therefore decorates it by composition instead of extending it — which is what its docblock always claimed it did.

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
+echo $stats->registeredServices, ' services, ', $stats->memoryUsageFormatted();
```

`ContainerStats` is `final readonly` and does not implement `ArrayAccess`, so array indexing fatals rather than degrading. The keys were snake_case; the properties are camelCase: `registeredServices`, `frozenServices`, `factoryServices`, `bindings`, `cachedDependencies`, `memoryUsageBytes` — plus `memoryUsageFormatted()` for the human-readable string the old `memory_usage` key held.

That is the point of the change. The array came straight from the container package, which explicitly excludes its shape from backward compatibility ("do not build logic on it"), so indexing it was never safe — this makes a rename fail at analysis time instead of in a user's terminal.

Not to be confused with `Container::getStats()`, a different method on the container itself, which still returns the array and is unchanged for all of 1.x.

---

## Deprecated in 2.0, removed in 3.0

Not blocking this upgrade, but the notices start now.

**Resolving a pillar from a `@method` docblock, or by scanning the caller's `use` statements**, raises `E_USER_DEPRECATED`. Declare it with `#[ServiceMap(method: ..., className: ...)]` — the attribute is checked first, so adding it silences the notice.

The notice fires on a **cold resolve only**, because the answer is memoized per caller-and-method. To surface every occurrence:

```bash
vendor/bin/gacela cache:clear
```

or develop with the file cache off.

---

## Not changing

- **`Container::getStats()`** — the method on the container itself — still returns its array and is unchanged for all of 1.x. Gacela's own `debug:container` moved to the typed `stats(): ContainerStats`, because upstream excludes the array's shape from its backward-compatibility promise; that is good advice for your code too, but nothing is forcing it. Do not confuse this with `ConsoleFacade::getContainerStats()`, which **did** change — see step 9 above
- **Config, facades, factories, `#[Provides]`, contextual bindings** — untouched
