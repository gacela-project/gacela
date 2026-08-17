# Module Boundaries

Module A may only reach module B through B's Facade. This page collects everything that enforces that claim and the agreements built on top of it: two opt-in analyser rules, a dependency-cycle gate on `debug:graph`, a declared rules file both readers share, and a CI review for graph changes.

The rules run under PHPStan and Psalm alike; [Static analysis](static-analysis.md) covers installing the analysers, the always-on pillar rules, and suppression.

## The cross-module rules

This is the one check that cannot be on by default: nothing in a class name says where a module boundary falls, so it needs your root namespace.

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
            ignoreReceivers:      # optional: receivers a call may land on, whatever module they are in
                - App\Modules\Shop\GlobalEnvironmentInterface
            publicApiSegments:    # optional: sub-namespaces a module publishes (see below)
                - Shared
                - Transfer
                - Dto
                - Event
```

### Psalm

One `<crossModule>` element enables both halves:

```xml
<plugins>
    <pluginClass class="Gacela\Psalm\Plugin">
        <crossModule rootNamespace="App\Modules" modulePathSegments="1">
            <sharedNamespace>App\Modules\Shared</sharedNamespace>
            <ignoreReceiver>App\Modules\Shop\GlobalEnvironmentInterface</ignoreReceiver>
            <publicApiSegment>Shared</publicApiSegment>
            <publicApiSegment>Transfer</publicApiSegment>
        </crossModule>
    </pluginClass>
</plugins>
```

A `<crossModule>` without a `rootNamespace` is a configuration error and stops the run. A rule that quietly does nothing is worse than no rule: it reads as a green check, and nothing would ever tell you the boundary went unchecked.

### What it accepts

- `sharedNamespaces` entries are exempt in both directions: references into them are always allowed, and classes inside them are not checked. Matching is namespace-boundary aware — `App\Modules\Shared` does not exempt `App\Modules\SharedFoo`.
- A **call** on a `*Facade` or a `*FacadeInterface` is allowed; consumers type-hint the interface, which is the same sanctioned crossing. A **written reference** is allowed only for `*Facade`, because naming `SomeFacadeInterface::class` is not a call through one.
- A **call on a `Throwable`** is allowed, always. A module throws its own exception type and a neighbour catches it and asks for `getMessage()`: that is reading, not collaborating, and the boundary a Facade protects is not crossed by it. Without this, every `catch` block of a typed exception is a finding — 24 of the 53 the rule raised on a 1100-file codebase were exactly that. The *named* reference is still reported: `new ShopException(...)` writes another module's name, which is the other half's business.
- `ignoreReceivers` entries are receivers a **call** may land on, whatever module they belong to — the value objects a project has decided are public contracts. Matched by `is_a()`, so naming an interface covers what implements it and naming a base covers what extends it. It is read by the method-call half only: the other half matches written names, and a name is not a receiver.
- `ignoreReceivers` is a constructor parameter rather than a screenful of `ignoreErrors`, which is PHPStan's own convention for a structural rule and is not the same thing: an error-message ignore also silences the *next* crossing that happens to be phrased the same way, and says nothing about which decision it is recording.
- A class the owning module **publishes** is allowed by both halves — carrying `#[PublicApi]`, or living under one of the `publicApiSegments`. That is [what a module exports](#what-a-module-exports), and it is the one exemption written next to the class rather than in the analyser configuration. For a class your project owns, prefer it over an `ignoreReceivers` entry; that list is for third-party types you cannot annotate.
- A receiver the analyser cannot resolve is not reported. An unknown type is not evidence of a violation, and guessing there would make the rule noise.
- One line can produce two findings — `(new ShopService())->run()` both names the other module and calls into it. Those are two crossings with two corrections, so both are reported.

To see the actual module dependency graph of your app, run `vendor/bin/gacela debug:graph` (formats: `text`, `mermaid`, `graphviz`, `json`).

## What a module exports

A module's public surface is wider than its Facade. DTOs, enums, value objects, events, plugin contracts — a Facade that returns an invoice has published the invoice too, and reading it in another module is what it was returned for.

There are two ways to say so, both read by the same boundary and therefore by both analysers.

### `#[PublicApi]`

```php
use Gacela\Framework\Attribute\PublicApi;

#[PublicApi]
final class InvoiceRecord
{
    // ...
}
```

The escape hatch for the odd class, declared where the class already lives so nothing has to move. It works on classes, interfaces and enums.

It is **not inherited**: publishing a base class would publish everything anyone ever extends from it, which is the opposite of a module deciding what it exports. Mark each exported type.

Classes written by `dto:generate` carry it already. A declared shape is data crossing a boundary by definition, and its own header says not to edit the file.

### The namespace convention

A configurable list of sub-namespace **segment names** — by default `Shared`, `Transfer`, `Dto`, `Event` — that a module publishes by construction. `App\Billing\Shared\Invoice` and `App\Billing\Domain\Dto\Money` are both exported with no annotation anywhere.

Segments are matched **whole, at any depth**, never as prefixes: `Event` publishes `App\Billing\Event\` and leaves `App\Billing\EventHandler\` exactly where it was. A class sitting directly in its module — `App\Billing\BillingFacade` — is never published by the convention.

Configure it with `publicApiSegments` (PHPStan) or `<publicApiSegment>` (Psalm), on both cross-module rules. An **explicitly empty** list turns the convention off and leaves `#[PublicApi]` as the only way to export — in Psalm, write a single empty `<publicApiSegment/>`, since leaving the element out means "use the default".

This is not the same idea as `sharedNamespaces`, and both are useful. A shared namespace is a *fully-qualified prefix that belongs to no module* — a shared kernel, exempt in both directions. A public-api segment is the name of a *sub-namespace under each module*, and a class in one still belongs to the module that owns it.

### Reading the surface back

```bash
vendor/bin/gacela debug:module Billing
```

prints a `Public API` section listing what that module exports, attribute-declared and convention-matched together, or `(none)`. It is also in the `--json` document, under `publicApi`. The surface is declared class by class, which is the right place to write it and the wrong place to read it from.

### What it does *not* do

Publishing a class says it may be touched **without going through the Facade**. It does not say the two modules may be coupled at all — that is what [the declared rules file](#declaring-which-modules-may-depend-on-which) answers, and `DeclaredModuleDependencyRule` is deliberately not exempted by `#[PublicApi]`. A forbidden edge stays forbidden whatever sits at the end of it.

There is a practical reason as well as a conceptual one. `debug:graph --check` enforces the same rules file through a different engine — module-to-module edges built from `use` imports — which cannot see an attribute at all. Exempting in the analyser alone would leave the editor green and the CI gate red on the same line, and a boundary that holds in one place and not the other is a boundary nobody trusts.

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

`debug:graph` with no `--check` stays exit-code-neutral, so adding the gate does not change what the command already did. The one exception is passing an option only `--check` reads: `--allowed-cycles` and `--rules` are refused without it, rather than accepted and ignored — a run that wrote a rules file and forgot the flag used to print the graph and exit zero, which is a gate that looks green while checking nothing.

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

**Only `debug:graph --check --rules` reports that**, and it is worth a CI step for it alone:

```
✗ Module rule governs nothing: App\Paymnet matches no module. Remove the rule, or fix the namespace.
```

Neither analyser can. They are handed one class at a time and asked whether *this* dependency is allowed; deciding that a namespace matches **no** module needs the whole graph, which only the command builds. So a rule with a typo in it passes PHPStan and Psalm in silence, enforcing nothing — the precise failure the paragraph above is about. The JSON report names them under `unknownRuleNamespaces`.

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

## See also

- [Static analysis](static-analysis.md) — analyser setup, the pillar rules, typed accessors, suppression
- [CLI commands](cli.md) — every `debug:graph` flag
