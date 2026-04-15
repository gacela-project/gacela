# RFC-0001: `#[Inject]` and `#[ServiceMapTyped]` — Symfony DI interop

- Status: **Proposed** — not yet approved. Implementation is gated on this RFC.
- Supersedes: —
- Blocks: `feat/inject-attribute` (PR #8 in `local/pr-plan.md`)

## 1. Context

Consumers of Gacela facades frequently see one of three patterns in their code:

```php
// Pattern A — verbose typed lookup
$fs = Gacela::getRequired(FilesystemFacade::class);

// Pattern B — service map attribute, loses interface typing
final class MyClass
{
    #[ServiceMap(FilesystemFacade::class)]
    private FilesystemFacade $fs;
}

// Pattern C — direct facade access with suppressions
/** @psalm-suppress InternalMethod */
$fs = $this->getFacade()->clearCache();
```

All three work, but none reads as natural constructor injection. Downstream
projects (phel-lang is the reference case) have dozens of Symfony `Command`
classes that could drop `ServiceResolverAwareTrait` and `getFacade()` calls
in favour of a single constructor-injected interface.

The proposal is to add `#[Inject]` on constructor parameters and
`#[ServiceMapTyped]` on properties, both targeting interfaces, and have
the container resolve them automatically. Psalm and PHPStan then infer
types without `@psalm-suppress` or manual docblocks.

## 2. Problem

Three open questions must be resolved before implementation. Each one
has ergonomic and runtime consequences; the cost of picking the wrong
answer is BC breakage or a silently-wrong DI wiring.

### Q1. Who wires Symfony `Command` constructors?

Phel's commands live inside a Symfony `Application`. Symfony autowires
`Command` constructors through its own container. If Gacela's `#[Inject]`
attribute also wires them, the two containers race — last-writer wins,
and the user gets silent mis-wiring that only surfaces at runtime.

**Option A.** Gacela owns the commands. Ship a Symfony compiler pass
(`GacelaInjectCompilerPass`) that inspects each `Command` service
definition: if any parameter has `#[Inject]`, route that parameter's
resolution to Gacela's container. Fail the build when both containers
bind the same parameter.
- Pros: clean consumer story (`#[Inject]` just works in Symfony classes).
- Cons: adds a hard dep on Symfony DI (at least in the interop package);
  compiler passes are the right extension point but raise the learning
  curve for debugging wiring issues.

**Option B.** `#[Inject]` only applies to non-Symfony classes. Symfony
commands keep using `Gacela::getRequired()` in `configure()` /
`execute()`. Document the boundary explicitly.
- Pros: no Symfony dep; two containers stay decoupled.
- Cons: the biggest target audience (phel's commands) doesn't benefit —
  the whole feature becomes much smaller in impact.

**Option C.** Ship a separate `gacela/symfony-bridge` package that
provides the compiler pass, and keep `#[Inject]` container-agnostic in
the core. Users opt-in by requiring the bridge.
- Pros: core stays thin; Symfony coupling is explicit and optional;
  other frameworks (Laravel, Slim, Mezzio) can ship their own bridges.
- Cons: one more package to publish and version; users must discover it.

**Recommended**: C. It keeps the core decoupled and gives phel a
narrow bridge they can adopt or fork. If the bridge footprint is small
enough (< ~200 LOC), it could be a subfolder inside gacela that ships
as a separate composer package via path-based autoload.

### Q2. What does `#[ServiceMapTyped]` return — proxy, binding, or hint?

`#[ServiceMapTyped(interface: FilesystemFacadeInterface::class)]` needs
to hand back a value that satisfies the interface *and* routes internal
method calls through the container's existing facade dispatch.

**Option A — Runtime proxy.** Generate an `$ interface`-implementing
proxy class on first resolution that forwards every method call to
`Gacela::getRequired($concrete)`. Psalm/PHPStan see the interface
type; internal `@internal` methods go through Gacela normally.
- Pros: zero call-site awareness of the proxy.
- Cons: generated code adds a file-cache entry per interface; reflection
  cost on boot; debugging traces show proxy methods.

**Option B — Binding.** Register the interface → concrete mapping in
the container at Provider time, so `Gacela::getRequired(Interface)`
returns the concrete. `#[ServiceMapTyped]` becomes a typed alias on
top of existing `addBinding()`.
- Pros: no code generation; same runtime cost as today's `getRequired`.
- Cons: doesn't address the `@psalm-suppress InternalMethod` pattern —
  callers still pierce `@internal` when they call concrete methods
  directly. Requires users to call only interface methods.

**Option C — Static-analysis hint only.** `#[ServiceMapTyped]` does
nothing at runtime — it's purely a marker for Psalm/PHPStan extensions
to upgrade the inferred type of `Gacela::getRequired($interface)` to
the concrete class.
- Pros: no runtime cost; no proxy; no new wiring.
- Cons: requires shipping a Psalm plugin and a PHPStan extension (both
  already exist in `src/PHPStan/` — scope-check whether extending them
  is within this RFC's budget).

**Recommended**: B for `#[Inject]` (binding resolution), C for
`#[ServiceMapTyped]` (static-analysis hint). That keeps runtime code
simple and solves both problems without generating proxy classes.
Runtime proxies (A) are deferred unless a concrete need emerges.

### Q3. `debug:dependencies` output layout

Current `DebugDependenciesCommand` reports `#[ServiceMap]` properties
per class. After `#[Inject]` ships, the same command must surface
constructor-injected deps.

**Option A.** Add a new "Injected parameters" section per class, below
the existing "Service-mapped properties" section.

**Option B.** Unify into a single "Dependencies" section; distinguish
with a column (`kind: inject | service-map | contextual-binding`).

**Option C.** Leave `debug:dependencies` untouched; ship a new
`debug:injected` command.

**Recommended**: B. A single unified view matches what users actually
want (one command, full picture of a class's DI graph). Requires a
small cs fix to the existing command's output format.

## 3. Decision (to be filled after approval)

> **Pending.** Fill this section before opening PR #8.

## 4. Alternatives considered (not recommended)

- **Status quo.** Keep `Gacela::getRequired()` everywhere. Rejected
  because the `@psalm-suppress InternalMethod` pattern is spreading
  and will only accumulate.
- **Remove `@internal` markers on facade methods.** Cheaper than any of
  the above but breaks the existing encapsulation contract — `@internal`
  exists precisely to gate which methods are user-facing vs framework-
  internal. Rejected.
- **Ship `#[Inject]` on properties only.** Property injection sidesteps
  constructor wiring but prevents `readonly` properties and hides
  dependencies. Rejected on modern-PHP grounds.

## 5. Consequences

### Positive

- Phel's `src/php/*/Infrastructure/Command/*.php` (~10 classes) drop
  `ServiceResolverAwareTrait` and `$this->getFacade()` calls.
- No more `@psalm-suppress InternalMethod` on consumer facade calls
  when they use `#[Inject]` with an interface.
- `debug:dependencies` becomes a single source of truth for a class's
  DI graph.

### Negative

- If Q1 lands on Option C (bridge package), consumers pay a
  discovery tax: "why doesn't `#[Inject]` work in my Symfony command?"
- Static-analysis hints (Q2 Option C) require users to have the Psalm
  plugin / PHPStan extension enabled, or they lose the type upgrade.

### Migration

Backwards-compatible by construction. Existing `Gacela::getRequired()`,
`#[ServiceMap]`, and `getFacade()` call sites continue to work.

## 6. Scope for PR #8 (when this RFC is approved)

- `#[Inject]` attribute + constructor wiring in Gacela's container.
- `#[ServiceMapTyped]` as a typed variant of `#[ServiceMap]`.
- `debug:dependencies` output update per Q3.
- Psalm plugin + PHPStan extension updates if Q2-C is chosen.
- The Symfony bridge per Q1-C, if chosen — may be a follow-up PR rather
  than part of #8.
- Documentation in `docs/container-configuration.md` with a migration
  example.

## 7. Open questions (for reviewers)

1. Is the Q1-C bridge-package split the right scope boundary, or should
   the core take a direct Symfony dep?
2. For Q2, is shipping a Psalm plugin update in the same PR acceptable,
   or does that warrant a separate PR?
3. Any other framework containers (Laravel, Mezzio) that should be
   considered now rather than left to future bridges?

## 8. References

- `local/phel-lang-feature-proposals.md` §6 — original proposal
- `local/pr-plan.md` — PR #7 (this RFC) blocks PR #8
- Existing `#[ServiceMap]` attribute in `src/Framework/Container/`
- Existing PHPStan rules in `src/PHPStan/`
