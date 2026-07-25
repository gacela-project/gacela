# RFC 0002 — How many ways are there to obtain a dependency?

**Status:** draft · **Relates to:** #525, #503, #480, #523

## Why this exists

Field data from [phel-lang](https://github.com/phel-lang/phel-lang), a production Gacela
consumer, reported "15+ documented ways to obtain a dependency" and a `GacelaConfig` with
33 public methods. Both were checked against this repository rather than taken on trust:

```
GacelaConfig public instance methods: 34  (33 excluding __construct)
```

The count is exact. This document is the inventory behind it, because the 2.0 question —
*which single path do we teach?* — cannot be answered without first writing down what
exists. Fifteen ways to do one thing is not flexibility; it is fifteen things a reader must
recognise before they can follow a wiring path, and fifteen surfaces we keep working forever.

Every entry below was read out of the source, not remembered.

## The inventory

Grouped by what the developer is actually trying to do. **P** = primary (documented,
taught, type-safe), **S** = supported (works, not the answer to "how do I…"),
**D** = deprecated, **I** = `@internal`.

### Intent 1 — reach another module

| # | Path | Class | | Notes |
|---|---|---|---|---|
| 1 | `$this->getFacade()` resolved from `@method` docblock | `ServiceResolverAwareTrait` | S | PHPStan reads the docblock natively |
| 2 | `$this->getFacade()` resolved from `#[ServiceMap]` | `ServiceResolverAwareTrait` | **P** | Typed by the reflection extension since #521 |
| 3 | `$this->getFacade()` resolved by scanning `use` statements | `DocBlockResolver::searchClassOverUseStatements()` | S | Invisible to every static analyser; file-parsing at runtime |
| 4 | `new SomeFacade()` directly | — | S | What `CrossModuleViaFacadeRule` permits and `FactoryDoesNotCallFacadeRule` forbids in a Factory |

Paths 1–3 are the *same call* resolved three different ways, in a documented order, at
runtime. That is the "convention re-derived per process" problem in one line.

### Intent 2 — get a collaborator inside my own module

| # | Path | Class | | Notes |
|---|---|---|---|---|
| 5 | Hand-written `create*()` returning `new X(...)` | `AbstractFactory` | **P** | The original model; explicit, untyped by nothing |
| 6 | `$this->make(X::class, [...])` | `AbstractFactory` | **P** | Autowires through the container (1.20, #499) |
| 7 | `$this->singleton(...)` | `AbstractFactory` | S | Per-factory memoisation |
| 8 | `$this->getFactory()` | `AbstractFacade` | **P** | Real typed method with a generic parameter |

### Intent 3 — get an external / infrastructure service

| # | Path | Declared in | | Notes |
|---|---|---|---|---|
| 9 | `getProvidedDependency('key')` after `provideModuleDependencies()` | `AbstractProvider` | S | **100% of consumer call sites annotate the result by hand** — see #523 |
| 10 | `getProvidedDependency('key')` after a `#[Provides]` method | `AbstractProvider` | **P** | Attribute-first since #502 |
| 11 | `addBinding(Interface::class, Impl::class)` | `GacelaConfig` | **P** | Then reached by autowiring or `make()` |
| 12 | `addBindingIf(...)` | `GacelaConfig` | S | Plugin defaults an app can override |
| 13 | `addMappingInterface(...)` | `GacelaConfig` | **D** | Deprecated since 1.2.0; `E_USER_DEPRECATED` since 1.20; removed in #480 |
| 14 | `addFactory('id', fn)` | `GacelaConfig` | S | New instance per resolution |
| 15 | `addProtected('id', fn)` | `GacelaConfig` | S | Stores the closure itself |
| 16 | `addAlias('short', Full::class)` | `GacelaConfig` | S | |
| 17 | `addLazy(...)` | `GacelaConfig` | S | |
| 18 | `when(X)->needs(Y)->give(Z)` | `GacelaConfig` | S | Contextual bindings |
| 19 | `extendService('id', fn)` | `GacelaConfig` | S | Decoration |
| 20 | `addExternalService()` / `getExternalService()` | `GacelaConfig` | S | Bootstrap-time escape hatch |
| 21 | `#[Inject]` on a constructor parameter | consuming class | **P** | |
| 22 | `#[Singleton]` on a class | consuming class | S | Lifetime, not lookup |
| 23 | `#[Factory]` on a class | consuming class | S | Lifetime, not lookup |
| 24 | `$container->get('id')` inside a Provider | `Gacela\Container\Container` | S | 35 public methods, most never surfaced by Gacela |
| 25 | `Locator::getInstance()` / `AnonymousGlobal` | framework | **I** | Both marked `@internal`; reachable regardless |

### Intent 4 — read a config value

| # | Path | Class | | Notes |
|---|---|---|---|---|
| 26–31 | `get()`, `getString()`, `getInt()`, `getFloat()`, `getBool()`, `getArray()` | `AbstractConfig` | **P** | Six typed getters, one intent — this is the *good* case: same path, typed variants |

## What the count actually says

**25 paths** for "obtain a dependency" (intents 1–3), of which:

- **8** are primary
- **15** are supported-but-not-taught
- **1** is deprecated
- **1** is `@internal` and reachable anyway

The phel figure of "15+" was, if anything, conservative.

Two observations that matter more than the total:

**Intent 1 is one call with three resolution strategies.** Paths 1–3 are not choices a
developer makes; they are fallbacks the runtime tries in order. A consumer who writes no
docblock and no attribute still gets a working `getFacade()` — resolved by parsing their
own source file at runtime. That is the single strongest argument in the 2.0 brief, and it
is not a "too many options" problem, it is a "the option is invisible" problem.

**Intent 4 is the counter-example, and it is instructive.** Six methods, one intent, and
nobody complains — because they are the *same path* with typed variants, discovered
together, and impossible to use wrongly. The problem in intents 1–3 is not the number of
methods; it is that the methods are unrelated mechanisms competing for the same job.

## Proposal

Not "delete 15 methods". Most have a real use, and 2.0 is not a rewrite.

1. **Name one primary path per intent** and make the docs teach only that:
   - reach another module → `#[ServiceMap]` + `getFacade()`
   - own collaborator → `create*()`, or `make()` when autowiring pays
   - external service → `#[Provides]`, or `addBinding()` for an interface
   - config value → `AbstractConfig::get*()`
2. **Retire the `use`-statement scan** (path 3). It is the only resolution strategy no
   static analyser can see, it costs a file read and a regex per cold resolve, and it exists
   solely so that omitting both the docblock and the attribute still works. Deprecate in
   1.x, remove in 2.0. This is the one *removal* this RFC actually argues for.
3. **Segregate `GacelaConfig` by audience** rather than shrinking it — bootstrap, container
   bindings, testing helpers, plugins. A consumer wiring one service should see ~8 methods,
   not 33. Interface segregation, no deletions, non-breaking if the concrete class keeps
   implementing all of them.
4. Leave the supported paths supported, and stop documenting them as alternatives.

## Open questions

1. Does retiring path 3 break anyone in practice? It is the *default* for a class with
   neither a docblock nor an attribute, so the blast radius is "every consumer who wrote
   neither" — unknown, and worth measuring before committing.
2. Is `Locator`/`AnonymousGlobal` being `@internal` but reachable acceptable, or should 2.0
   make it unreachable? Making it private is breaking for anyone who ignored the marker.
3. Does segregating `GacelaConfig` help if the concrete class still implements every
   interface? The discoverability win is real; the "33 methods on one object" fact is not
   changed by it.
