# Static Analysis

Gacela's architecture is a set of claims — a Facade only delegates, a Factory wires its own module, module A reaches module B only through B's Facade. Those claims are worth no more than what checks them, so the checks ship **with the framework**, for PHPStan and Psalm alike.

Both analysers run the same rules. There is one implementation of each check in `Gacela\StaticAnalysis`; `Gacela\PHPStan` and `Gacela\Psalm` are thin adapters over it. So the two cannot drift apart on what counts as a violation, and neither can fall behind the framework it checks — see [why they ship here](#why-the-rules-ship-with-the-framework).

## Setup

### PHPStan

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
| A Factory does not `new` a Facade | `gacela.factoryInstantiatesFacade` | `GacelaFacadeInstantiation` | on |
| A Factory does not call `$this->getFacade()` | `gacela.factoryCallsGetFacade` | `GacelaFactoryFacadeAccess` | on |
| A Facade's public methods are in its `*FacadeInterface` | `gacela.facadeInterfaceDrift` | `GacelaFacadeInterfaceDrift` | on |
| A cross-module reference the source **names** | `gacela.crossModuleWithoutFacade` | `GacelaCrossModuleAccess` | opt-in |
| A cross-module call the source does **not** name | `gacela.crossModuleMethodCall` | `GacelaCrossModuleMethodCall` | opt-in |
| A dependency the project's rules file forbids | `gacela.declaredModuleDependency` | `GacelaDeclaredModuleDependency` | opt-in |

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

A `@method CheckoutFacade getFacade()` docblock works too — both analysers read those natively — but then the same fact is written twice, and the copies drift.

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

## Module boundaries

Module A may only reach module B through B's Facade. This is the one check that cannot be on by default: nothing in a class name says where a module boundary falls, so it needs your root namespace.

It comes in **two halves, meant to be enabled together**.

The first matches the module names a source *writes* — a `new`, a static call, a class constant, a static property. The second resolves the receiver of a method call by **type**, because that is how a boundary actually gets crossed once dependencies go through Providers and constructors:

```php
public function __construct(
    private readonly InvoiceRepository $invoices,  // App\Billing — another module
) {
}

public function createProcessor(): Processor
{
    return new Processor($this->invoices->findAll());  // names nothing here
}
```

The class appears once, in a type-hint. A check that only matched written names would report green on exactly the codebases most likely to be crossing boundaries.

### PHPStan

```neon
services:
    -
        class: Gacela\PHPStan\Rules\CrossModuleViaFacadeRule
        tags: [phpstan.rules.rule]
        arguments:
            rootNamespace: App\Modules
            modulePathSegments: 1     # how many segments under the root identify a module
            sharedNamespaces:         # optional shared kernels, exempt from the check
                - App\Modules\Shared
    -
        class: Gacela\PHPStan\Rules\CrossModuleMethodCallRule
        tags: [phpstan.rules.rule]
        arguments:
            rootNamespace: App\Modules
            modulePathSegments: 1
            sharedNamespaces:
                - App\Modules\Shared
```

### Psalm

```xml
<plugins>
    <pluginClass class="Gacela\Psalm\Plugin">
        <crossModule rootNamespace="App\Modules" modulePathSegments="1">
            <sharedNamespace>App\Modules\Shared</sharedNamespace>
        </crossModule>
    </pluginClass>
</plugins>
```

A `<crossModule>` without a `rootNamespace` is a configuration error and stops the run. A rule that quietly does nothing is worse than no rule: it reads as a green check, and nothing would ever tell you the boundary went unchecked.

### What it accepts

- `sharedNamespaces` entries are exempt in both directions: references into them are always allowed, and classes inside them are not checked. Matching is namespace-boundary aware — `App\Modules\Shared` does not exempt `App\Modules\SharedFoo`.
- A **call** on a `*Facade` or a `*FacadeInterface` is allowed; consumers type-hint the interface, which is the same sanctioned crossing. A **written reference** is allowed only for `*Facade`, because naming `SomeFacadeInterface::class` is not a call through one.
- A receiver the analyser cannot resolve is not reported. An unknown type is not evidence of a violation, and guessing there would make the rule noise.
- One line can produce two findings — `(new ShopService())->run()` both names the other module and calls into it. Those are two crossings with two corrections, so both are reported.

To see the actual module dependency graph of your app, run `vendor/bin/gacela debug:graph` (formats: `text`, `mermaid`, `graphviz`, `json`).

### One place the two analysers differ

A facade whose public method comes from a **trait** is judged by PHPStan and not by Psalm:

```php
final class CheckoutFacade extends AbstractFacade
{
    use LogicTrait;   // PHPStan reports logic in here, Psalm does not
}
```

Both run the same rule; the difference is what each host hands a plugin. PHPStan analyses a trait's methods once **per class that uses it**, so the rule sees the method with the facade as its class. Psalm analyses them once, in the **trait's own** context — a trait extends nothing, and a trait-provided method does not appear in the using class's AST.

There is no route to a trait method's body in a using class's context through Psalm's public plugin API, so this is a limitation to know about rather than something a future release quietly fixes. If your facades take methods from traits, PHPStan is the analyser that checks them.

## Failing on dependency cycles

`debug:graph --check` exits non-zero when two modules depend on each other:

```bash
vendor/bin/gacela debug:graph --check
```

A cycle is either a decision somebody made or a mistake nobody noticed, and until the decision is written down those are the same thing. Write it down in a JSON file and pass it in:

```json
[
    {
        "modules": ["App\\Billing", "App\\Invoicing"],
        "reason": "reviewed 2026-07: bidirectional by design until the shared kernel lands"
    }
]
```

```bash
vendor/bin/gacela debug:graph --check --allowed-cycles=allowed-module-cycles.json
```

The allow list is **self-invalidating**: an entry that no longer matches a real cycle fails the check just as loudly as an undeclared cycle. That is deliberate. An allow-list that outlives what it allows stops being a record of a decision and becomes a mute button, and nothing would tell you it had happened. A `reason` is required for the same reason — an allowance nobody justified is indistinguishable from a cycle nobody noticed.

`debug:graph` with no `--check` stays exit-code-neutral, so adding the gate does not change what the command already did.

## Declaring which modules may depend on which

A cycle is the only thing the graph can refuse on its own. Everything else a team agrees on — billing must not reach back-office, reporting reads and nothing more — lives in prose, where no tool can see it and a violation arrives as one more import in a diff. Write it in a JSON file instead:

```json
{
    "rules": [
        {
            "from": "App\\Payment",
            "deny": ["App\\Admin"],
            "reason": "reviewed 2026-08: billing must not reach back-office"
        },
        {
            "from": "App\\Reporting",
            "allow": ["App\\Shared"],
            "reason": "read-only module: the shared kernel and nothing else"
        }
    ]
}
```

- `deny` forbids the listed modules and leaves every other dependency alone.
- `allow` is the opposite reading: those are the **only** modules reachable, and anything else is a violation. An empty `allow` is meaningful — a leaf module that may depend on nothing.
- One entry cannot carry both, and a rule with no `reason` is refused.
- A rule about `App\Payment` also governs `App\Payment\Refunds`, and matching is namespace-boundary aware: `App\Pay` never governs `App\Payment`.

The same file is read in two places. In CI, over the whole graph:

```bash
vendor/bin/gacela debug:graph --check --rules=module-rules.json
```

and in the editor, per class, by whichever analyser you run:

```neon
# phpstan.neon
services:
    -
        class: Gacela\PHPStan\Rules\DeclaredModuleDependencyRule
        tags: [phpstan.rules.rule]
        arguments:
            rootNamespace: App
            rulesFile: %currentWorkingDirectory%/module-rules.json
```

```xml
<!-- psalm.xml -->
<pluginClass class="Gacela\Psalm\Plugin">
    <moduleRules rootNamespace="App" file="module-rules.json"/>
</pluginClass>
```

One file, two readers, on purpose: a boundary that holds in CI and not in the editor is a boundary nobody trusts.

The rules are **self-invalidating**, like the cycle allow list. A `from`, `allow` or `deny` naming a namespace that matches no module fails the check — a rule about a module nobody has any more still reads as a boundary being watched. A `deny` that never fires is not an error; that is the rule doing its job.

`--rules` cannot be combined with a filter argument: in a narrowed graph, a rule about a filtered-out module is indistinguishable from a rule about a module that no longer exists, and those two must not look alike.

`--check --format=json` writes the findings as a report instead of lines, for a CI job that wants more than an exit code:

```json
{
    "undeclaredCycles": [],
    "staleAllowedCycles": [],
    "forbiddenDependencies": [
        {"from": "App\\Payment", "to": "App\\Admin", "reason": "reviewed 2026-08: billing must not reach back-office"}
    ],
    "unknownRuleNamespaces": []
}
```

## Reviewing graph changes in CI

A new cross-module edge enters a pull request as one more `use` statement, which is exactly as visible as every other import. `--compare-to` turns it into something a reviewer can see:

```bash
# on the base branch
vendor/bin/gacela debug:graph --format=json > base-graph.json

# on the branch under review
vendor/bin/gacela debug:graph --compare-to=base-graph.json > graph-diff.md
```

The report is GitHub-flavoured markdown with a mermaid block GitHub renders natively in a comment, listing new and removed dependencies and drawing only the modules the change touches. When the graph is unchanged it writes **nothing** and exits `0` — so a CI job can test the file for emptiness and stay quiet on the pull requests that did not move the graph. An unreadable or invalid baseline exits `1`: that is a broken setup, not an unchanged graph, and the two must not look alike.

`.github/workflows/module-graph.yml` in this repository is a working example.

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

Its `EnforceModuleBoundariesForMethodCallRule` is `CrossModuleMethodCallRule` here, and the boundary check now has a second half — the references a source names — that the package never covered. See [Module boundaries](#module-boundaries) for the configuration.

## Troubleshooting

- **PHPStan can't find the file** — verify the include path resolves relative to your `phpstan.neon`.
- **Psalm ignores the include** — ensure `xmlns:xi="http://www.w3.org/2001/XInclude"` is declared, then `vendor/bin/psalm --clear-cache`.
- **A rule fires on the framework's own words** — `GacelaConfig` is a bootstrap builder, not a pillar. `psalm.xml` and `phpstan.neon` in this repository show the scoped suppression.

## See also

- [PHPStan: ignoring errors](https://phpstan.org/user-guide/ignoring-errors)
- [Psalm configuration](https://psalm.dev/docs/running_psalm/configuration/)
- [Gacela ServiceMap](https://gacela-project.com/docs/service-map/)
