# Upgrading

Everything deprecated in the 1.x line still works. Each entry below is scheduled
for removal in 2.0, so migrating now is optional but keeps the 2.0 upgrade
mechanical.

## Deprecated in 1.x, removed in 2.0

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
people miss.

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
