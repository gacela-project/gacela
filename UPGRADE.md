# Upgrading

## 1.21 → 2.0

Ordered by how likely each change is to affect you. The first three are mechanical renames a project of any size will hit; the last two only bite specific shapes of code.

If you are not ready, **1.21 is feature-complete** and deliberately ships the tooling for this migration — the typed pillar accessors, `FacadeInterfaceInSyncRule`, and `doctor`'s filename check all landed in 1.x so you can do the work *before* the major, not as a reward for taking it.

### Before you start

```bash
composer require gacela-project/gacela:^1.21   # if you are not already there
vendor/bin/gacela doctor                        # names the files 2.0 will stop resolving
vendor/bin/gacela cache:clear                   # deprecation notices are memoized per caller
```

Run your test suite on 1.21 with deprecations visible. Every `E_USER_DEPRECATED` from Gacela is a thing 2.0 either changed or will remove in 3.0.

---

### 1. PHP 8.3

The floor is `>=8.3` (was `>=8.1`). PHP 8.1 reached end of life in December 2025 and 8.2's security window closes in December 2026, so a 2.0 pinned to either would ship already needing another bump.

Nothing to change in your code — but if you cannot move off 8.1 or 8.2, stay on 1.21.

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

The trait was renamed in 1.x and the old name kept as an alias; 2.0 drops the alias.

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

`gacela-project/container` moved to `^1.2.1` (was `^0.10.0`), and `Gacela\Container\Container` is `final` from 1.0. `Gacela\Framework\Container\Container` therefore decorates it by composition instead of extending it — which is what its docblock always claimed it did.

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

- **`getStats()`** on the container still works and is unchanged for all of 1.x. Gacela's own `debug:container` moved to the typed `stats(): ContainerStats`, because upstream excludes the array's shape from its backward-compatibility promise — good advice for your code too, but nothing is forcing it
- **Config, facades, factories, `#[Provides]`, contextual bindings** — untouched
