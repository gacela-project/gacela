# Static Analysis

Gacela's architecture is a set of claims — a Facade only delegates, a Factory wires its own module, module A reaches module B only through B's Facade. Those claims are worth no more than what checks them, so the checks ship **with the framework**, for PHPStan and Psalm alike.

Both analysers run the same rules. There is one implementation of each check in `Gacela\StaticAnalysis`; `Gacela\PHPStan` and `Gacela\Psalm` are thin adapters over it. So the two cannot drift apart on what counts as a violation, and neither can fall behind the framework it checks — see [why they ship here](#why-the-rules-ship-with-the-framework).

## Setup

### PHPStan

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer) there is nothing to do: requiring Gacela registers the rules and the accessor typing. Turn that off for this package alone with:

```json
{
    "extra": {
        "phpstan/extension-installer": {
            "ignore": ["gacela-project/gacela"]
        }
    }
}
```

Without the installer, include it yourself:

```neon
includes:
    - vendor/gacela-project/gacela/phpstan-gacela.neon
```

### Psalm

```xml
<?xml version="1.0"?>
<psalm
    xmlns:xi="http://www.w3.org/2001/XInclude"
    xmlns="https://getpsalm.org/schema/config"
>
    <projectFiles>
        <directory name="src"/>
    </projectFiles>

    <plugins>
        <pluginClass class="Gacela\Psalm\Plugin"/>
    </plugins>

    <xi:include href="vendor/gacela-project/gacela/psalm-gacela.xml"/>

    <issueHandlers>
        <InvalidArgument>
            <errorLevel type="suppress">
                <directory name="src" />
            </errorLevel>
        </InvalidArgument>
    </issueHandlers>
</psalm>
```

The `InvalidArgument` suppression is required — Gacela resolves concrete types at runtime that Psalm can't infer statically. Suppress inline if you prefer narrower scope:

```php
/** @psalm-suppress InvalidArgument */
return new YourService($this->getConfig());
```

The `<plugins>` block cannot be delivered through the XInclude: XInclude replaces a single element, and `<plugins>` lives elsewhere in your config. It is also the part that matters — `psalm-gacela.xml` only *suppresses* `UndefinedMagicMethod`, and a suppressed call is not a checked one. The plugin replaces the suppression with real types.

## What is checked

Each rule reports under a PHPStan error identifier and a Psalm issue class; both are what you suppress on, so a rule can be turned off on its own.

| Check | PHPStan identifier | Psalm issue | |
|---|---|---|---|
| `*Facade`/`*Factory`/`*Provider`/`*Config` extends its pillar base | `gacela.suffixExtends` | `GacelaSuffixExtends` | on |
| A Facade method only delegates | `gacela.facadeOnlyDelegates` | `GacelaFacadeOnlyDelegates` | on |
| A `#[Cacheable]` key mentions the arguments | `gacela.cacheableKeyIgnoresArguments` | `GacelaCacheableKeyIgnoresArguments` | on |
| A `#[Cacheable]` method reaches `$this->cached()` | `gacela.cacheableWithoutCachedCall` | `GacelaCacheableWithoutCachedCall` | on |
| A Factory does not `new` a Facade | `gacela.factoryInstantiatesFacade` | `GacelaFacadeInstantiation` | on |
| A Factory does not call `$this->getFacade()` | `gacela.factoryCallsGetFacade` | `GacelaFactoryFacadeAccess` | on |
| A Facade's public methods are in its `*FacadeInterface` | `gacela.facadeInterfaceDrift` | `GacelaFacadeInterfaceDrift` | on |
| A cross-module reference the source **names** | `gacela.crossModuleWithoutFacade` | `GacelaCrossModuleAccess` | opt-in |
| A cross-module call the source does **not** name | `gacela.crossModuleMethodCall` | `GacelaCrossModuleMethodCall` | opt-in |
| A dependency the project's rules file forbids | `gacela.declaredModuleDependency` | `GacelaDeclaredModuleDependency` | opt-in |
| A pillar accessor declared with `#[ServiceMap]`, not `@method` | `gacela.serviceMapMissing` | `GacelaServiceMapMissing` | opt-in |

On top of the rules, both analysers gain two **types** they otherwise lack: the pillar accessors, and `getProvidedDependency()` by class-string.

Every finding carries the correction as well as the complaint — PHPStan renders it on its own 💡 line, Psalm appends it to the message, because it has nowhere else to put it:

```
Class App\Checkout\CheckoutFacade should extend Gacela\Framework\AbstractFacade
    💡 Extend Gacela\Framework\AbstractFacade, or rename it so it does not end in Facade.
```

The pillar rules apply to **classes**. An interface, trait or enum named after a pillar is left alone: none of them can extend a class, so there would be no way to act on the report.

Suppressing one rule:

```neon
# phpstan.neon
parameters:
    ignoreErrors:
        -
            identifier: gacela.suffixExtends
            path: src/Legacy/*
```

### Holding a declared kind to its base

`SuffixExtendsRule` takes a suffix and the parent it expects, and `phpstan-gacela.neon` registers it once per pillar. A [kind you declared](getting-a-dependency.md#resolve-a-kind-of-my-own) is one more block in your own config:

```neon
# phpstan.neon
services:
    -
        class: Gacela\PHPStan\Rules\SuffixExtendsRule
        tags: [phpstan.rules.rule]
        arguments:
            suffix: Exporter
            expectedParent: App\Shared\AbstractExporter
```

Gacela does not register this for you: the kind is yours, so is the base, and a rule the framework invented about your classes would be a rule you never asked for.

```xml
<!-- psalm.xml -->
<issueHandlers>
    <PluginIssue name="GacelaSuffixExtends">
        <errorLevel type="suppress">
            <directory name="src/Legacy"/>
        </errorLevel>
    </PluginIssue>
</issueHandlers>
```

## Typed pillar accessors

Declare the pillar with `#[ServiceMap]` and the accessor gets a real return type under both analysers:

```php
#[ServiceMap(method: 'getFacade', className: CheckoutFacade::class)]
final class CheckoutController
{
    use ServiceResolverAwareTrait;

    public function __invoke(): Response
    {
        // Both analysers know this is a CheckoutFacade, and check the call on it.
        return $this->getFacade()->placeOrder();
    }
}
```

This matters more than it looks. The accessor was previously *suppressed* rather than typed, and a suppressed call is not a checked one — it evaluates to `mixed`, which silently switches off analysis of everything reached through it, not just the accessor itself. A typo in `placeOrder()` produced no error at all.

A `@method CheckoutFacade getFacade()` docblock works too — both analysers read those natively — but then the same fact is written twice, and the copies drift. The one reason to write both is the IDE, which reads `@method` and not the attribute; that tradeoff is described in [reach another module](getting-a-dependency.md#reach-another-module).

## IDE metadata

The analysers type `$this->getProvidedDependency(Clock::class)` as `Clock`, and leave a string id as `mixed` on purpose. An editor has neither, because the method signature says `mixed`. `ide:meta` writes what the editor can read:

```bash
vendor/bin/gacela ide:meta            # write .phpstorm.meta.php/gacela.meta.php
vendor/bin/gacela ide:meta --dry-run  # report what would change, write nothing
```

The generated file states two things:

- **An id that names a class or interface resolves to it.** One rule, no scanning, always true — the same thing both analysers do.
- **Each string id, typed by the return type of the `#[Provides]` method that registers it.** This is type information neither analyser produces.

The file is generated: add `.phpstorm.meta.php/` to `.gitignore` and regenerate it after changing a Provider. `doctor` compares its content against the attributes and reports when they no longer match. The directory form is deliberate, so a project's own hand-written root meta file is left alone.

Two kinds of id are deliberately left out, and `ide:meta` lists the first kind rather than guessing:

- **Ids two providers register with different types.** The editor map is keyed by argument value across the whole application, while `getProvidedDependency()` reads the calling module's own container. One entry would be right in one module and wrong in the other, and a wrong type is worse than an absent one — it type-checks a call that fails.
- **Ids whose provider method returns `array`, a nullable or a union.** A map value is a class name; `Foo` for a method returning `?Foo` would hide exactly the null the caller has to handle.

That second exclusion is not rare. Gacela's own `ConsoleProvider` returns `array` from every `#[Provides]` method, so running `ide:meta` on this repository types zero string ids — the wildcard is the whole of what it gains. The string-id half pays off in applications that provide objects.

One caveat this cannot verify for you: whether your editor resolves `Provider::BILLING_FACADE` to its value before consulting the map. If it does not, the string-id entries only apply at call sites that pass the literal.

**The PHPStan suppression is gone as of 2.0.** `phpstan-gacela.neon` no longer carries an `ignoreErrors` entry for undeclared pillar accessors, so a class that declares neither `#[ServiceMap]` nor a `@method` docblock has its `$this->getFacade()` reported as an undefined method. Psalm still ships its suppression in `psalm-gacela.xml` as a fallback, scheduled for removal in 3.0.

## Typed provided dependencies

Ask for a provided dependency by class-string and it comes back typed:

```php
// Both analysers know this is a Clock, and check the call on it.
$clock = $this->getProvidedDependency(Clock::class);
```

`getProvidedDependency()` is declared as returning `mixed`, which is why call sites end up with a hand-written `@var` above them — an assertion the analyser takes on faith, and which keeps claiming the old type after the Provider changes. When the key *is* a class-string, the type was never unknown; it was discarded at the boundary.

A string key (`$this->getProvidedDependency('some.service')`) still returns `mixed`. Nothing in the type system says what it resolves to, and a guess would be worse than `mixed`: `mixed` is honestly unknown, a guess is confidently wrong and then trusted.

A Factory may also declare its dependencies in its **constructor**; pillars are resolved through the container, so autowiring applies to the Factory itself:

```php
final class CheckoutFactory extends AbstractFactory
{
    public function __construct(
        private readonly Clock $clock,
    ) {
    }
}
```

## Facade interfaces

If you type-hint against a `*FacadeInterface` rather than the concrete facade, the interface-drift rule keeps the pair honest: a public facade method missing from the interface is reported.

Only that direction can drift. PHP already rejects a class that fails to implement an interface method, so the interface cannot gain a method the facade lacks — but the facade grows public methods the interface never hears about, and consumers holding the interface silently cannot reach them. That stays invisible until someone compares the two files, and by then the fix is a breaking change.

The rule is on by default and self-limiting: it only fires for a facade that explicitly implements the interface named after it (`FooFacade` implements `FooFacadeInterface`). A facade that implements unrelated interfaces, or none, is not checked.

## Finding what 3.0 removes

Resolving a pillar accessor from a `@method` docblock, or from the file's `use` statements, is deprecated in 2.0 and removed in 3.0 — `#[ServiceMap]` is the replacement. The runtime says so, but only for accessors a run actually reaches, and only on a **cold** resolve: the answer is memoized per caller-and-method, so a warm cache is silent. A migration driven by those notices is a migration over whichever code paths your tests happen to execute.

This rule reads the same fact from the source, for every class at once:

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

It is opt-in because what it reports is not wrong on 2.x. Turning it on is the decision to start the migration, taken when the project is ready rather than as a side effect of upgrading.

Each finding carries the attribute to paste, with the type spelled as the docblock spells it — the file it goes back into is the one whose imports made the short name resolve in the first place:

```
App\Wallet\WalletCommand::getFacade() is resolved from its @method docblock, which is deprecated and removed in 3.0
    💡 Declare it with #[ServiceMap(method: 'getFacade', className: WalletFacade::class)].
```

Deliberately narrow, since it runs inside your build: only classes using `ServiceResolverAwareTrait` **directly**, and only a `@method` naming a method the class does not declare — a real method never reaches `__call()`. Keep the `@method` tag if your editor wants it; the attribute is what resolves, and the two together are fine.

## Module boundaries

The two opt-in cross-module rules, the dependency-cycle gate on `debug:graph`, the declared module rules file, and the CI graph review have their own page: [Module boundaries](module-boundaries.md). Enable the rules there once the analyser setup above is in place.

Both cross-module rules leave a module's own public API alone: a class carrying `#[PublicApi]`, or one under a sub-namespace the module publishes by convention. The convention is configured on both rules and defaults to `Shared`, `Transfer`, `Dto`, `Event`:

| | PHPStan | Psalm |
|---|---|---|
| Configured with | `publicApiSegments:` (a list) | `<publicApiSegment>` (one element each) |
| Left out | the default list applies | the default list applies |
| Turned off | an explicit `publicApiSegments: []` | a single empty `<publicApiSegment/>` |

The values are namespace **segment names** matched whole under each module, not namespace prefixes — `Event` publishes `App\Billing\Event\` and leaves `App\Billing\EventHandler\` alone. See [what a module exports](module-boundaries.md#what-a-module-exports) for the attribute, the convention, and why the declared-dependency rule is deliberately not exempted by either.

## One place the two analysers differ

A facade whose public method comes from a **trait** is judged by PHPStan and not by Psalm:

```php
final class CheckoutFacade extends AbstractFacade
{
    use LogicTrait;   // PHPStan reports logic in here, Psalm does not
}
```

Both run the same rule; the difference is what each host hands a plugin. PHPStan analyses a trait's methods once **per class that uses it**, so the rule sees the method with the facade as its class. Psalm analyses them once, in the **trait's own** context — a trait extends nothing, and a trait-provided method does not appear in the using class's AST.

There is no route to a trait method's body in a using class's context through Psalm's public plugin API, so this is a limitation to know about rather than something a future release quietly fixes. If your facades take methods from traits, PHPStan is the analyser that checks them.

## Why the rules ship with the framework

Rather than as separate `phpstan-extension` / `psalm-plugin` packages, which is the more usual arrangement. Three reasons, and one piece of evidence.

**One implementation per rule.** `Gacela\StaticAnalysis` holds the checks; `Gacela\PHPStan` and `Gacela\Psalm` adapt them to a host. Split the adapters into separate packages and that shared core has to live somewhere — back here anyway, in a third package, or duplicated. Two copies of "what counts as the same module" would drift, which is the failure the interface-drift rule exists to catch.

**Gacela analyses itself with them.** `phpstan.neon` includes `phpstan-gacela.neon` and `psalm.xml` registers the plugin, so every rule runs against the framework's own source on every build. Separate packages make that a circular dependency, and a rule nobody runs is a rule nobody notices breaking.

**Lockstep is the point.** These rules name `AbstractFacade`, `AbstractFactory` and the rest. They are a description of this framework's architecture at this version, not a general-purpose tool with its own release cycle.

**The evidence:** `gacela-project/phpstan-extension` was that separate package. It stopped at PHPStan 1, builds errors without the identifiers PHPStan 2 requires, and so cannot load against the PHPStan version Gacela itself needs. Its one rule now lives here as `CrossModuleMethodCallRule`.

## Migrating from `gacela-project/phpstan-extension`

That package is abandoned. Everything it did is built in, and more.

```bash
composer remove --dev gacela-project/phpstan-extension
```

| `phpstan-extension` | Built in |
|---|---|
| `includes: …/phpstan-extension/extension.neon` | `includes: …/gacela/phpstan-gacela.neon` |
| `parameters.gacela.modulesNamespace` | `rootNamespace`, on the two cross-module rules |
| `parameters.gacela.excludedNamespaces` | `sharedNamespaces`, on the same two rules |

Its `EnforceModuleBoundariesForMethodCallRule` is `CrossModuleMethodCallRule` here, and the boundary check now has a second half — the references a source names — that the package never covered. It also takes an `ignoreReceivers` list the package had no equivalent for, and exempts calls on a `Throwable` without being asked: an exception a neighbour catches and reads is not a boundary crossing, and reporting it made every `catch` of a typed exception a finding. See [Module boundaries](module-boundaries.md) for the configuration.

## Troubleshooting

- **PHPStan can't find the file** — verify the include path resolves relative to your `phpstan.neon`.
- **Psalm ignores the include** — ensure `xmlns:xi="http://www.w3.org/2001/XInclude"` is declared, then `vendor/bin/psalm --clear-cache`.
- **A rule fires on the framework's own words** — `GacelaConfig` is a bootstrap builder, not a pillar. `psalm.xml` and `phpstan.neon` in this repository show the scoped suppression.

## See also

- [Module boundaries](module-boundaries.md) — the cross-module rules, cycle gate, and rules file
- [PHPStan: ignoring errors](https://phpstan.org/user-guide/ignoring-errors)
- [Psalm configuration](https://psalm.dev/docs/running_psalm/configuration/)
- [Gacela ServiceMap](https://gacela-project.com/docs/service-map/)
