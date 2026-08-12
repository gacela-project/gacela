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
```

### Psalm

One `<crossModule>` element enables both halves:

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

## See also

- [Static analysis](static-analysis.md) — analyser setup, the pillar rules, typed accessors, suppression
- [CLI commands](cli.md) — every `debug:graph` flag
