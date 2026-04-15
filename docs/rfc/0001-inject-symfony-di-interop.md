# RFC-0001: `#[Inject]` and Symfony DI interop

- Status: **Accepted** (2026-04-15). Supersedes the prior `Proposed` draft.
- Blocks: `feat/inject-attribute` (PR #8 in `local/pr-plan.md`).
- Revision note: the prior draft misrepresented `#[Inject]` as new and
  described a property-level `#[ServiceMap]` that does not exist. Both
  issues are corrected here; see §1.2 for existing-state facts.

## 1. Context

### 1.1 Pain points

Gacela consumers today use one of three patterns to reach services from
outside a facade or factory:

```php
// Pattern A — verbose typed lookup at the call site
$fs = Gacela::getRequired(FilesystemFacade::class);

// Pattern B — class-level dispatch map (current `#[ServiceMap]`)
#[ServiceMap(method: 'getFs', className: FilesystemFacade::class)]
final class MyClass
{
    use ServiceResolverAwareTrait;
    // $this->getFs() works via __call magic
}

// Pattern C — direct facade access with Psalm suppressions
/** @psalm-suppress InternalMethod */
$fs = $this->getFacade()->clearCache();
```

None of these reads as natural constructor injection. Phel's Symfony
commands (~10 `Command` classes under
`src/php/*/Infrastructure/Command/`) carry `ServiceResolverAwareTrait`
boilerplate and `@psalm-suppress` comments that `#[Inject]` can delete.

### 1.2 What already exists

- `Gacela\Container\Attribute\Inject` is defined in the vendor container
  package (`vendor/gacela-project/container/src/Container/Attribute/Inject.php`)
  with target `Attribute::TARGET_PARAMETER` and an optional
  `?string $implementation` parameter for interface → concrete override.
- `DependencyResolver::resolveDependenciesRecursively()` (same package)
  already honors it: if `#[Inject($concrete)]` is present, it resolves
  `$concrete`; otherwise it falls through to the parameter's type-hint
  (autowire) or a default value.
- Constructor autowiring-by-type-hint works today for any Gacela-managed
  construction path — `#[Inject]` is an optional override, not a switch
  to turn injection on.
- `Gacela\Framework\` contains **zero** references to `Inject`. The
  attribute is effectively undocumented and undiscoverable.
- `#[ServiceMap(method, className)]` (`src/Framework/ServiceResolver/ServiceMap.php`)
  targets `TARGET_CLASS`, is repeatable, and declares class-level
  dispatch-map entries consumed by `ServiceResolverAwareTrait::__call()`.
  It is **not** a property attribute and has no interface-typing mode.

The prior RFC draft conflated these two attributes and proposed a new
`#[ServiceMapTyped]`. Given `#[Inject]` already exists and already
supports interface → concrete override, no second attribute is needed.

### 1.3 Existing DI infrastructure referenced below

| Concern | Today |
| --- | --- |
| Bindings | `GacelaConfig::addBinding(Interface, Impl)` → `BindingResolver` |
| Contextual bindings | `GacelaConfig::when(Consumer)->needs(Interface)->give(Impl)` |
| Protected services | `GacelaConfig::addProtected(id, Closure)` — stored raw, not instantiated |
| Not-found error | `ServiceNotFoundException` (thrown by `Gacela::getRequired()`) |
| Wrong-param-shape error | `DependencyInvalidArgumentException` (thrown at reflection time) |
| `debug:dependencies` | `src/Console/Infrastructure/Command/DebugDependenciesCommand.php` — renders constructor params with a `ParameterStatus` enum tag per row |
| Reflection caching | `DependencyResolver::constructorCache` (per-process); `AbstractPhpFileCache` (cross-process) |

## 2. Problem

Three questions must be resolved before PR #8 opens. Each has a
committed decision in §3.

### Q1. Who wires Symfony `Command` constructors?

Phel's commands live inside a Symfony `Application`. Symfony autowires
`Command` constructors through its own container. If Gacela's
`#[Inject]` also wires them, the two containers race — last-writer
wins, and the user gets silent mis-wiring that surfaces only at runtime.

- **Option A.** Gacela core owns commands via a built-in Symfony
  compiler pass. Hard dep on `symfony/dependency-injection`.
- **Option B.** `#[Inject]` skips Symfony-owned classes; commands keep
  using `Gacela::getRequired()` in `execute()`.
- **Option C.** Separate `gacela/symfony-bridge` package that ships the
  compiler pass. Core stays decoupled; Symfony coupling is explicit and
  optional.

### Q2. One attribute or two?

The prior draft proposed `#[Inject]` for constructor parameters and
`#[ServiceMapTyped]` for properties. With `#[Inject]` already
implemented in the container and constructor injection the modern-PHP
norm, a second property attribute doubles the surface area for a use
case we have no concrete consumer for.

- **Option A.** Ship only `#[Inject]` (constructor, targeting
  parameters). Defer property injection until a consumer asks for it.
- **Option B.** Extend `#[Inject]` to target both parameters and
  properties; property injection happens in a post-construct pass.
- **Option C.** Introduce `#[ServiceMapTyped]` as a property-only
  attribute orthogonal to `#[Inject]`.

### Q3. `debug:dependencies` output

`DebugDependenciesCommand` already prints constructor parameters with
per-row `ParameterStatus` (bound / autowirable / default / scalar /
missing). After #8, some rows are `#[Inject]`-annotated (possibly with
an override).

- **Option A.** Add a new section for injected parameters.
- **Option B.** Add a `kind` column to the existing rows: `inject`,
  `contextual`, `bound`, `autowirable`, `default`, `scalar`, `missing`.
- **Option C.** New `debug:injected` command.

## 3. Decision

### Q1 → Option C, with lockstep release

A new `gacela/symfony-bridge` package ships `GacelaInjectCompilerPass`.
Core stays Symfony-free. **The bridge MUST release alongside PR #8** so
the phel use case works on day one; shipping `#[Inject]` without a
working bridge would leave the headline consumer unable to adopt it.

The compiler pass walks Symfony service definitions; for every
constructor parameter carrying `#[Inject]`, it routes resolution to
Gacela's container and removes Symfony's autowire claim for that slot.
If both containers claim the same parameter the pass fails the build
with a message identifying the service and parameter.

The bridge may live in a `symfony-bridge/` subfolder of this repo and
ship as a separate composer package via path-based autoload during
development; it splits into its own repo once stable.

### Q2 → Option A

Ship `#[Inject]` only, constructor parameters only. The attribute
already exists in `Gacela\Container\Attribute\Inject` and is honored by
`DependencyResolver`. PR #8 promotes it, not implements it: docs,
discoverability, Symfony bridge, static-analysis type upgrade,
`debug:dependencies` surfacing, migration examples.

**`#[ServiceMapTyped]` is dropped from this RFC.** No concrete consumer
for property injection exists; constructor injection is the modern-PHP
norm and interoperates cleanly with `readonly`. If a future consumer
emerges, a follow-up RFC can extend `#[Inject]` to `TARGET_PROPERTY`
without breaking any of the guarantees in this one.

### Q3 → Option B

Extend the existing per-row status with a `kind` column. One command,
one view, minimal code churn. `ParameterStatus` gains an `INJECT` value
and the renderer interleaves the override target (the `implementation`
argument) when present.

## 4. Specification

### 4.1 Attribute shape (no code change required)

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

This is the existing class. PR #8 adds docs, examples, and a
re-export-via-documentation under `Gacela\Framework\Attribute\Inject`
(class alias in bootstrap) so the user-facing namespace is consistent
with `#[Cacheable]` and the rest of `Gacela\Framework\Attribute\`.

### 4.2 Resolution order

For a parameter `#[Inject($override)] Type $p` inside a class
`Consumer` being constructed by the container:

1. If `$override` is set (`#[Inject(ConcreteImpl::class)]`) → resolve `$override`.
2. Else if `$config->when(Consumer)->needs(Type)->give(X)` → resolve `X`.
3. Else if `$config->addBinding(Type, X)` → resolve `X`.
4. Else if `Type` is instantiable → `new Type(...)` with recursive autowire.
5. Else if `$p` has a default value → use the default.
6. Else → throw `ServiceNotFoundException`.

Nullable parameters (`?FooInterface`) with no binding and no default
resolve to `null`. This is the one place `null` is a legitimate result;
every other miss is an exception.

### 4.3 Error surface

| Condition | Exception |
| --- | --- |
| `#[Inject]` on a parameter without a type hint | `DependencyInvalidArgumentException::noParameterTypeFor` (existing) |
| `#[Inject]` on a scalar-typed parameter with no default | `DependencyInvalidArgumentException::unableToResolve` (existing) |
| `#[Inject($x)]` where `$x` is not a class-string | `DependencyInvalidArgumentException` (new helper) |
| Resolution falls through every step and the type is not instantiable | `ServiceNotFoundException` (existing) |
| Target is a protected service (see §4.4) | `ServiceNotFoundException` (existing behavior) |

### 4.4 Interactions with existing DI features

- **Protected services.** `$config->addProtected(id, Closure)` stores
  the closure without invoking it. A `#[Inject]`-annotated parameter
  cannot resolve to a protected id — protected services are designed
  not to be instantiated by the container, and injecting one would
  change its semantics. This requires no new code: the container's
  existing resolution path already throws `ServiceNotFoundException`
  when a protected id is requested as a service.
- **Contextual bindings.** `$config->when(...)->needs(...)->give(...)`
  takes precedence over global bindings per §4.2 step 2 vs step 3.
- **`ContainerFixture` trait.** `resetContainer()` clears
  `DependencyResolver::constructorCache` and any cross-process
  constructor cache. `captureContainerState()` / `restoreContainerState()`
  treat the resolver caches as part of the container snapshot.
- **`#[ServiceMap]` and `ServiceResolverAwareTrait`.** Untouched. The
  class-level dispatch map is an orthogonal mechanism for `__call`-based
  lookup and remains the right tool for that use case.

### 4.5 Static analysis

- `#[Inject(ConcreteImpl::class)]` on an interface-typed parameter
  hints Psalm and PHPStan that the runtime value is `ConcreteImpl`.
  The existing rule set in `src/PHPStan/` is extended to upgrade the
  inferred type on the annotated parameter's usages inside the
  constructor body.
- `#[Inject]` without an `implementation` override → no upgrade;
  analyzers trust the declared type hint.
- A user who does not install the PHPStan rules / Psalm plugin loses
  the type upgrade but keeps runtime correctness.

### 4.6 Reflection caching

- Per-process: `DependencyResolver::constructorCache` already memoizes
  `ReflectionClass::getConstructor()` per class — no change needed.
- Cross-process: when file cache is enabled, a lightweight
  `ConstructorInjectionsCache` (new, follows the `AbstractPhpFileCache`
  pattern) stores per-class `#[Inject]` metadata so boot reflection
  scans are O(changed classes), not O(all classes). Implementation
  detail of PR #8; not a public contract. The cache participates in
  `cache:clear` and `cache:warm` like the other `AbstractPhpFileCache`
  instances.

### 4.7 Symfony bridge (`gacela/symfony-bridge`)

- New composer package, shipped with PR #8.
- Single class of note: `GacelaInjectCompilerPass implements CompilerPassInterface`.
- Registered by users in their Symfony kernel (`$container->addCompilerPass(new GacelaInjectCompilerPass())`).
  The bridge ships a bundle or extension class that registers the pass
  automatically when the bundle is enabled; the bare compiler pass
  remains available for projects that don't use bundles.
- The pass runs **before** Symfony's autowire pass so the rewritten
  service definitions are stable.
- **Conflict rule.** If a parameter is claimed by both a Gacela
  `#[Inject]` and Symfony's own autowire/bind, the pass fails the
  build with a message naming the service id and parameter name.
- Parameters without `#[Inject]` are left to Symfony untouched.

## 5. Migration example

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

After (with `gacela/symfony-bridge` installed and the pass registered):

```php
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

- `ServiceResolverAwareTrait` is gone.
- `@psalm-suppress InternalMethod` is gone.
- The constructor lists the dependency — tooling (`debug:dependencies`,
  IDE autocomplete, static analysis) sees it.

## 6. Scope for PR #8

Budget: **M**, not L (the prior draft budgeted L based on the belief
that `#[Inject]` needed to be built; it already exists).

In scope:

1. Bootstrap-time `class_alias(Gacela\Container\Attribute\Inject::class, Gacela\Framework\Attribute\Inject::class)`.
2. `ConstructorInjectionsCache` (cross-process `#[Inject]` metadata).
3. PHPStan rule(s) upgrading interface types when `#[Inject(Concrete::class)]` is present.
4. `debug:dependencies` per Q3 (new `kind` column, extended `ParameterStatus`).
5. `docs/container-configuration.md` section on `#[Inject]` with the
   migration example.
6. `gacela/symfony-bridge` package with `GacelaInjectCompilerPass`,
   bundle glue, and tests against a minimal Symfony kernel.
7. CHANGELOG under `Unreleased > Added`.

Out of scope (deferred to future RFCs if a consumer emerges):

- Property-level `#[Inject]` (`TARGET_PROPERTY`).
- Laravel, Mezzio, or other framework bridges.
- Runtime proxies for interfaces without a concrete binding.

## 7. Alternatives considered (not recommended)

- **Status quo.** Keep `Gacela::getRequired()` everywhere. Rejected —
  `@psalm-suppress InternalMethod` is spreading in consumers.
- **Remove `@internal` markers on facade methods.** Cheaper but breaks
  the existing encapsulation contract. Rejected.
- **Ship `#[Inject]` on properties too.** Hides dependencies, prevents
  `readonly`. Rejected on modern-PHP grounds; see §3 Q2.
- **Bundle the Symfony bridge into core.** Pins Gacela to
  `symfony/dependency-injection`. Rejected; see §3 Q1.

## 8. Consequences

### Positive

- Phel's Symfony commands drop `ServiceResolverAwareTrait` and
  `@psalm-suppress` comments.
- `debug:dependencies` becomes the single source of truth for a class's
  DI graph (bindings, contextual bindings, `#[Inject]` overrides, and
  autowire, all in one table).
- `#[Inject]` becomes discoverable. Today it ships undocumented inside
  the container package.

### Negative

- Consumers on Symfony discover the bridge package separately. Mitigated
  by the docs migration example linking to it explicitly.
- Users without the PHPStan/Psalm plugins lose the type upgrade. The
  runtime behavior is identical with or without the plugins — only the
  inferred type at interface-typed `#[Inject(Concrete::class)]` sites
  degrades to the declared interface.

### Backwards compatibility

Fully compatible. `Gacela::getRequired()`, `#[ServiceMap]`,
`getFacade()`, `ServiceResolverAwareTrait`, and existing constructor
autowiring all continue to work unchanged. `#[Inject]` becomes an
additive opt-in.

## 9. References

- `local/phel-lang-feature-proposals.md` §6 — original proposal
- `local/pr-plan.md` — PR #7 (this RFC) blocks PR #8; PR #8 budget revised M
- `vendor/gacela-project/container/src/Container/Attribute/Inject.php` — existing attribute
- `vendor/gacela-project/container/src/Container/DependencyResolver.php` — existing runtime support
- `src/Framework/ServiceResolver/ServiceMap.php` — the unrelated class-level dispatch map
- `src/Console/Infrastructure/Command/DebugDependenciesCommand.php` — target of the Q3 change
