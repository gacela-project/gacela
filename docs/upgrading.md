# Upgrading

- [Upgrading to 2.0](#upgrading-to-20)
- [Deprecated in 1.x, removed in 2.0](#deprecated-in-1x-removed-in-20)

## Upgrading to 2.0

**Do this on 1.21 first.** Every deprecation in the [next
section](#deprecated-in-1x-removed-in-20) is *removed* in 2.0, and 1.21 emits a
runtime notice for each. Clearing them on 1.21 turns this upgrade into a version
bump; skipping that step turns it into a debugging session, because a pillar that
no longer resolves fails by being silently absent rather than by throwing.

1.21 is the final 1.x feature release and deliberately ships the tooling for this
migration: `doctor`'s filename check, `FacadeInterfaceInSyncRule`, and the typed
pillar accessors.

### 1. PHP 8.3 is the minimum

Was `>=8.1`. PHP 8.1 reached end of life in December 2025 and 8.2's security
window closes in December 2026, so a 2.0 pinned to either would ship already
needing another bump.

**Staying on 8.1 or 8.2?** Stay on **1.21**. It is feature-complete, not a
downgrade.

### 2. `symfony/*` is `^7.0 || ^8.0`

Was `^6.4`. Both majors are accepted, so Gacela does not decide your Symfony
version for you. Only the console commands depend on Symfony.

### 3. `gacela-project/container` is `^1.0`

`Gacela\Container\Container` is now `final`, so `Gacela\Framework\Container\Container`
composes it instead of extending it.

**Your providers are unaffected.** This still works exactly as before:

```php
public function provideModuleDependencies(Container $container): void
{
    $container->set(
        'FACADE_OTHER',
        static fn (Container $container) => $container->getLocator()->get(Other\Facade::class),
    );
}
```

The one thing that breaks is type-hinting the *upstream* concrete class where a
Gacela container is passed. Depend on the interface instead:

```diff
-use Gacela\Container\Container;
+use Gacela\Container\ContainerInterface;

-public function register(Container $container): void
+public function register(ContainerInterface $container): void
```

### 4. Undeclared pillar accessors are reported by PHPStan

1.x shipped an `ignoreErrors` entry in `phpstan-gacela.neon` that silenced
`Call to an undefined method ...::getFacade()`. It is gone.

A suppressed call was never a typed one: it evaluated to `mixed`, which switched
off checking of everything reached *through* the accessor. Declaring the pillar
gets that checking back.

```diff
+#[ServiceMap(method: 'getFacade', className: MyFacade::class)]
 final class MyConsumer
 {
     use ServiceResolverAwareTrait;
 }
```

A `@method` docblock works too — PHPStan reads it natively:

```php
/**
 * @method MyFacade getFacade()
 */
```

If you already declared your pillars on 1.21, this is a no-op for you.

### 5. Class constants are typed

`AbstractSetupGacela` and `ConfigInterface` declare types on their constants (a
PHP 8.3 feature). This only affects code that **redeclares** one with a different
type — previously silent, now a compile error.

### Behaviour fix worth knowing

`Gacela::resetCache()` now drops the memoized "this class does not exist"
answers. `ClassValidator` cached the negative result of `class_exists()`, so a
class that was not loadable when first resolved stayed "missing" for the life of
the process. This affects long-running workers (RoadRunner, Swoole, queue
consumers) that re-bootstrap, code generation, and `cache:warm` emitting classes.

Positive answers are deliberately kept: a class that exists cannot stop existing,
so they never go stale.

## Deprecated in 1.x, removed in 2.0

Everything below still works on 1.x and is **removed in 2.0**. Migrate on 1.21,
where each still runs.

| Deprecated | Replacement | Since |
|---|---|---|
| `AbstractDependencyProvider` | `AbstractProvider` | 1.8.0 |
| `GacelaConfig::addMappingInterface()` | `GacelaConfig::addBinding()` | 1.2.0 |
| `DocBlockResolverAwareTrait` | `ServiceResolverAwareTrait` | 1.12.0 |

Each emits an `E_USER_DEPRECATED` notice at runtime, except
`DocBlockResolverAwareTrait` — PHP gives no hook for "a trait was used", so that
one is documented only. Grep for it directly:

```bash
grep -rn "DocBlockResolverAwareTrait" src/
```

To see the others while running your test suite, make PHP report them:

```php
error_reporting(E_ALL);
```

### `AbstractDependencyProvider` → `AbstractProvider`

Change the parent class and rename the method:

```diff
-final class DependencyProvider extends AbstractDependencyProvider
+final class Provider extends AbstractProvider
 {
     public function provideModuleDependencies(Container $container): void
     {
         $container->set(SomeService::class, static fn () => new SomeService());
     }
 }
```

**Rename the file too.** `DependencyProvider.php` must become `Provider.php` —
Gacela resolves module pillars by filename suffix, so a class named `Provider`
sitting in `DependencyProvider.php` will not be found. This is the step most
people miss, and `gacela doctor` reports it.

2.0 also removes the internal `DependencyProviderResolver` and the dual
provider-resolution path it fed. A side effect worth knowing: on 1.x a module's
`provideModuleDependencies()` could run **twice**, because both resolvers shared
a normalized cache slot. If your provider body is not idempotent -- counters,
external registrations, logging -- it now runs once.

`provideModuleDependencies()` keeps working on `AbstractProvider`. If you would
rather register by attribute, `#[Provides]` is the attribute-first path:

```php
final class Provider extends AbstractProvider
{
    #[Provides]
    public function someService(): SomeService
    {
        return new SomeService();
    }
}
```

### `addMappingInterface()` → `addBinding()`

A straight rename; the arguments are identical.

```diff
-$config->addMappingInterface(SomeInterface::class, SomeImplementation::class);
+$config->addBinding(SomeInterface::class, SomeImplementation::class);
```

### `DocBlockResolverAwareTrait` → `ServiceResolverAwareTrait`

`DocBlockResolverAwareTrait` is now nothing but `use ServiceResolverAwareTrait;`,
so swapping it changes no behaviour.

```diff
-use DocBlockResolverAwareTrait;
+use ServiceResolverAwareTrait;
```
