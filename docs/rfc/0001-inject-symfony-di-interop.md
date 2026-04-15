# RFC-0001: `#[Inject]` and Symfony DI interop

- Status: **Accepted** (2026-04-15, amended 2026-04-15).
- Blocks: `feat/inject-attribute` (PR #8 in `local/pr-plan.md`).
- Revision log:
  - 2026-04-15 (amendment) — drop the `Gacela\Framework\Attribute\Inject`
    bootstrap alias from PR #8a. Runtime probe showed that
    `ReflectionParameter::getAttributes()` with exact-FQN match (how the
    vendor container reads `#[Inject]`) does not propagate through
    `class_alias`, so a user annotating with the aliased FQN would be
    silently ignored. The alias is deferred to a coordinated follow-up
    once the vendor container migrates to `ReflectionAttribute::IS_INSTANCEOF`
    on its `getAttributes()` calls. See §3.1 and §5.
  - 2026-04-15 (amendment) — drop `ConstructorInjectionsCache` from
    PR #8a. Audit showed no existing code scans constructor attributes
    at boot or in `cache:warm`, so the cache would have neither
    producer nor consumer — speculative infrastructure. Add it later
    only when a concrete consumer appears. See §3.6 and §5.

## 1. Context

Consumers today reach Gacela services from outside facades via
`Gacela::getRequired()`, `#[ServiceMap]` + `ServiceResolverAwareTrait`,
or direct `getFacade()` calls with `@psalm-suppress InternalMethod`.
None of these read as natural constructor injection. Phel's ~10 Symfony
`Command` classes are the reference target.

**Existing state (load-bearing for the decisions below):**

- `Gacela\Container\Attribute\Inject` already exists in the vendor
  container package, targets `TARGET_PARAMETER`, and takes an optional
  `?string $implementation` override.
- `DependencyResolver::resolveDependenciesRecursively()` already honors
  it; falls through to type-hint autowire otherwise.
- Nothing under `src/Framework/` references `Inject` — it is
  undocumented and undiscoverable.
- `#[ServiceMap(method, className)]` at
  `src/Framework/ServiceResolver/ServiceMap.php` targets `TARGET_CLASS`
  — a class-level `__call` dispatch map, unrelated to property typing.

PR #8's job is therefore to **promote** `#[Inject]`, not to implement it.

## 2. Decisions

### Q1. Who wires Symfony `Command` constructors? → Separate bridge, lockstep release.

A new `gacela/symfony-bridge` package ships `GacelaInjectCompilerPass`.
Core stays Symfony-free. **The bridge MUST release alongside PR #8** —
shipping `#[Inject]` without a working bridge leaves the headline
consumer (phel commands) unable to adopt it. The pass routes
`#[Inject]`-annotated parameters to Gacela and fails the build when
both containers claim the same parameter. May live in a
`symfony-bridge/` subfolder during development, split once stable.

### Q2. One attribute or two? → One. `#[Inject]`, constructor-only.

The proposed `#[ServiceMapTyped]` is dropped — no concrete consumer for
property injection, and constructor injection interoperates cleanly
with `readonly`. A follow-up RFC can extend `TARGET_PROPERTY` later
without breaking this one. PR #8 adds docs, static-analysis upgrade,
`debug:dependencies` surfacing, and migration examples around the
existing attribute. The canonical user-facing FQCN is
`Gacela\Container\Attribute\Inject` (see §3.1 amendment).

### Q3. `debug:dependencies` output? → One unified view with a `kind` column.

Extend the existing per-row status with a `kind` column (`inject`,
`contextual`, `bound`, `autowirable`, `default`, `scalar`, `missing`).
`ParameterStatus` gains `INJECT`; the renderer inlines the override
target when `#[Inject($impl)]` is set.

## 3. Specification

### 3.1 Attribute shape (no change required)

```php
namespace Gacela\Container\Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Inject
{
    public function __construct(
        public ?string $implementation = null,
    ) {}
}
```

The canonical user-facing FQCN is `Gacela\Container\Attribute\Inject`.

The prior draft proposed a bootstrap `class_alias` to expose this as
`Gacela\Framework\Attribute\Inject` for namespace consistency with
`#[Cacheable]`. That alias is **deferred**: PHP's
`ReflectionParameter::getAttributes()` with exact-FQN match (as used in
`DependencyResolver::resolveDependenciesRecursively()`) does not treat
`class_alias` targets as equivalent to the original class, so user code
written against the aliased FQN would be silently ignored at runtime.

The alias becomes viable once the vendor container
(`gacela-project/container`) switches its `getAttributes()` calls to
pass `ReflectionAttribute::IS_INSTANCEOF`. That is a separate,
coordinated PR and out of scope for #8a.

### 3.2 Resolution order

For `#[Inject($override)] Type $p` on `Consumer`:

1. `$override` set → resolve `$override`.
2. `$config->when(Consumer)->needs(Type)->give(X)` → resolve `X`.
3. `$config->addBinding(Type, X)` → resolve `X`.
4. `Type` instantiable → `new Type(...)` with recursive autowire.
5. `$p` has a default → use it.
6. Otherwise → throw `ServiceNotFoundException`.

Nullable parameters (`?Foo`) with no binding and no default resolve to
`null`. Every other miss is an exception.

### 3.3 Error surface

| Condition | Exception |
| --- | --- |
| `#[Inject]` on a parameter without a type hint | `DependencyInvalidArgumentException::noParameterTypeFor` |
| `#[Inject]` on a scalar type with no default | `DependencyInvalidArgumentException::unableToResolve` |
| `#[Inject($x)]` where `$x` is not a class-string | `DependencyInvalidArgumentException` (new helper) |
| Resolution exhausted, type not instantiable | `ServiceNotFoundException` |

### 3.4 Interactions

- **Protected services** (`$config->addProtected`) cannot be injected;
  the existing resolution path already throws `ServiceNotFoundException`
  for them.
- **Contextual bindings** win over global bindings (§3.2 step 2 before 3).
- **`ContainerFixture`**: `resetContainer()` clears the constructor
  cache; `captureContainerState()` / `restoreContainerState()` include it.
- **`#[ServiceMap]` and `ServiceResolverAwareTrait`** are untouched —
  orthogonal `__call` dispatch, different use case.

### 3.5 Static analysis

`#[Inject(ConcreteImpl::class)]` on an interface-typed parameter
upgrades the analyzer's inferred type to `ConcreteImpl`. Extend the
existing rule set in `src/PHPStan/`. Without the override, no upgrade —
analyzers trust the declared hint. Runtime behavior is identical with or
without the plugin.

### 3.6 Caching

`DependencyResolver::constructorCache` already memoizes reflection
per-process — no change needed. A cross-process `ConstructorInjectionsCache`
was originally scoped for PR #8 but **dropped**: nothing in the
codebase scans constructor attributes at boot or during `cache:warm`,
so the cache would have no producer or consumer. It is carrying
capacity, not value. Add it in a follow-up only if and when a concrete
consumer appears (e.g., a future `cache:warm` scanner or a
`debug:injected` command that lists every `#[Inject]` usage
repo-wide).

### 3.7 Symfony bridge (`gacela/symfony-bridge`)

- New composer package, ships with PR #8.
- `GacelaInjectCompilerPass implements CompilerPassInterface`, runs
  **before** Symfony's autowire pass.
- Bundle glue included for projects using Symfony bundles; the bare
  compiler pass remains available otherwise.
- Conflict (both containers claim a parameter) → build fails with the
  service id and parameter name.

## 4. Migration example

Before:

```php
final class PhelRunCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @psalm-suppress InternalMethod */
        $this->getFacade()->clearCache();
        return self::SUCCESS;
    }
}
```

After (bridge installed + pass registered):

```php
use Gacela\Container\Attribute\Inject;

final class PhelRunCommand extends Command
{
    public function __construct(
        #[Inject] private readonly PhelFacadeInterface $phel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->phel->clearCache();
        return self::SUCCESS;
    }
}
```

Trait gone. `@psalm-suppress` gone. Dependency visible to tooling.

## 5. Scope for PR #8

Budget **M** (not L — the attribute already exists).

In scope:

1. PHPStan rule for `#[Inject(Concrete::class)]` type upgrade.
2. `debug:dependencies` `kind` column + extended `ParameterStatus`.
3. `docs/container-configuration.md` section with the migration example
   (canonical import: `use Gacela\Container\Attribute\Inject;`).
4. `gacela/symfony-bridge` package with `GacelaInjectCompilerPass`,
   bundle glue, tests against a minimal Symfony kernel.
5. CHANGELOG under `Unreleased > Added`.

Out of scope (future coordinated PRs): `Gacela\Framework\Attribute\Inject`
alias (gated on vendor container migrating to `IS_INSTANCEOF` — see §3.1),
`ConstructorInjectionsCache` (deferred until a concrete consumer exists
— see §3.6), property-level `#[Inject]`, non-Symfony bridges, runtime
proxies for unbound interfaces.

## 6. Consequences

- **Positive.** Phel's Symfony commands drop `ServiceResolverAwareTrait`
  and `@psalm-suppress`. `#[Inject]` becomes discoverable.
  `debug:dependencies` becomes one source of truth for a class's DI graph.
- **Negative.** Symfony users discover the bridge separately (mitigated
  by docs). Users without the PHPStan rule lose the type upgrade;
  runtime is identical.
- **Backwards compatible.** `Gacela::getRequired()`, `#[ServiceMap]`,
  `getFacade()`, `ServiceResolverAwareTrait`, and existing autowiring
  all continue to work unchanged.

## 7. References

- `vendor/gacela-project/container/src/Container/Attribute/Inject.php`
- `vendor/gacela-project/container/src/Container/DependencyResolver.php`
- `src/Framework/ServiceResolver/ServiceMap.php`
- `src/Console/Infrastructure/Command/DebugDependenciesCommand.php`
