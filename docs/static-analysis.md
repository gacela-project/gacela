# Static Analysis

Gacela ships configs for PHPStan and Psalm covering the pillar accessors that
`ServiceResolverAwareTrait` resolves at runtime — `getFacade()`, `getFactory()`,
`getConfig()`.

## PHPStan

Include in your `phpstan.neon`:

```neon
includes:
    - vendor/gacela-project/gacela/phpstan-gacela.neon
```

### Typed pillar accessors

Declare the pillar with `#[ServiceMap]` and the accessor gets a real return type:

```php
#[ServiceMap(method: 'getFacade', className: CheckoutFacade::class)]
final class CheckoutController
{
    use ServiceResolverAwareTrait;

    public function __invoke(): Response
    {
        // PHPStan knows this is a CheckoutFacade, and checks the call on it.
        return $this->getFacade()->placeOrder();
    }
}
```

This matters more than it looks. The accessor was previously *suppressed* rather
than typed, and a suppressed call is not a checked one — it evaluates to `mixed`,
which silently switches off analysis of everything reached through it, not just
the accessor itself. A typo in `placeOrder()` produced no error at all.

A `@method CheckoutFacade getFacade()` docblock works too — PHPStan reads those
natively — but then the same fact is written twice, and the copies drift.

The suppression is still in `phpstan-gacela.neon` as a fallback for classes that
declare neither, and is scheduled for removal in 2.0.

### Typed provided dependencies

Ask for a provided dependency by class-string and it comes back typed:

```php
// PHPStan knows this is a Clock, and checks the call on it.
$clock = $this->getProvidedDependency(Clock::class);
```

`getProvidedDependency()` is declared as returning `mixed`, which is why call
sites end up with a hand-written `@var` above them — an assertion the analyser
takes on faith, and which keeps claiming the old type after the Provider
changes. When the key *is* a class-string, the type was never unknown; it was
discarded at the boundary.

A string key (`$this->getProvidedDependency('some.service')`) still returns
`mixed`. Nothing in the type system says what it resolves to, and inventing a
type there would be worse than `mixed` — a guess the analyser then trusts.

A Factory may also declare its dependencies in its **constructor**; pillars are
resolved through the container, so autowiring applies to the Factory itself:

```php
final class CheckoutFactory extends AbstractFactory
{
    public function __construct(
        private readonly Clock $clock,
    ) {
    }
}
```

### Facade interfaces

If you type-hint against a `*FacadeInterface` rather than the concrete facade,
`FacadeInterfaceInSyncRule` keeps the pair honest: a public facade method missing
from the interface is reported.

Only that direction can drift. PHP already rejects a class that fails to
implement an interface method, so the interface cannot gain a method the facade
lacks — but the facade grows public methods the interface never hears about, and
consumers holding the interface silently cannot reach them. That stays invisible
until someone compares the two files, and by then the fix is a breaking change.

The rule is on by default and self-limiting: it only fires for a facade that
explicitly implements the interface named after it (`FooFacade` implements
`FooFacadeInterface`). A facade that implements unrelated interfaces, or none,
is not checked.

### Module boundaries

The bundled `CrossModuleViaFacadeRule` enforces gacela's core architecture rule
statically: module A may only reach module B through B's Facade. It is opt-in —
register it with your project's root namespace:

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
```

- Any `new`, static call, class-constant or static-property reference from one
  module into another is reported unless the referenced class is a `*Facade`.
- `sharedNamespaces` entries are exempt in both directions: references into
  them are always allowed, and classes inside them are not checked. Matching is
  namespace-boundary aware (`App\Modules\Shared` does not exempt
  `App\Modules\SharedFoo`).

To see the actual module dependency graph of your app, run
`vendor/bin/gacela debug:graph` (formats: `text`, `mermaid`, `graphviz`, `json`).

### Failing on dependency cycles

`debug:graph --check` exits non-zero when two modules depend on each other:

```bash
vendor/bin/gacela debug:graph --check
```

A cycle is either a decision somebody made or a mistake nobody noticed, and
until the decision is written down those are the same thing. Write it down in a
JSON file and pass it in:

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

The allow list is **self-invalidating**: an entry that no longer matches a real
cycle fails the check just as loudly as an undeclared cycle. That is deliberate.
An allow-list that outlives what it allows stops being a record of a decision
and becomes a mute button, and nothing would tell you it had happened. A `reason`
is required for the same reason — an allowance nobody justified is
indistinguishable from a cycle nobody noticed.

`debug:graph` with no `--check` stays exit-code-neutral, so adding the gate does
not change what the command already did.

### Reviewing graph changes in CI

A new cross-module edge enters a pull request as one more `use` statement, which
is exactly as visible as every other import. `--compare-to` turns it into
something a reviewer can see:

```bash
# on the base branch
vendor/bin/gacela debug:graph --format=json > base-graph.json

# on the branch under review
vendor/bin/gacela debug:graph --compare-to=base-graph.json > graph-diff.md
```

The report is GitHub-flavoured markdown with a mermaid block GitHub renders
natively in a comment, listing new and removed dependencies and drawing only the
modules the change touches. When the graph is unchanged it writes **nothing** and
exits `0` — so a CI job can test the file for emptiness and stay quiet on the
pull requests that did not move the graph. An unreadable or invalid baseline
exits `1`: that is a broken setup, not an unchanged graph, and the two must not
look alike.

`.github/workflows/module-graph.yml` in this repository is a working example.

## Psalm

```xml
<?xml version="1.0"?>
<psalm
    xmlns:xi="http://www.w3.org/2001/XInclude"
    xmlns="https://getpsalm.org/schema/config"
>
    <projectFiles>
        <directory name="src"/>
    </projectFiles>

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

## Troubleshooting

- **PHPStan can't find the file** — verify the include path resolves relative to your `phpstan.neon`.
- **Psalm ignores the include** — ensure `xmlns:xi="http://www.w3.org/2001/XInclude"` is declared, then `vendor/bin/psalm --clear-cache`.

## See also

- [PHPStan: ignoring errors](https://phpstan.org/user-guide/ignoring-errors)
- [Psalm configuration](https://psalm.dev/docs/running_psalm/configuration/)
- [Gacela ServiceMap](https://gacela-project.com/docs/service-map/)
