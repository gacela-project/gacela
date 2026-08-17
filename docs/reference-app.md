# The reference application

Every feature in this repository has a fixture built for it. None of them
answered whether the features still compose: the last several fixes — a stale
cache, listeners lost between `gacela.php` and the bootstrap closure, unusable
remediations — were each found by hand-probing a scratch project, because no
fixture had the whole thing wired at once.

This is that project, inside the repository, run on every pull request.

It lives in [`tests/Feature/ReferenceApp/`](../tests/Feature/ReferenceApp), and
it is an invoicing SaaS: small enough to read in one sitting, large enough that
every capability has a place it belongs rather than a place it was put.

## The application

The application root is
[`tests/Feature/ReferenceApp/Invoicing/`](../tests/Feature/ReferenceApp/Invoicing)
— the directory holding `gacela.php`, `config/` and the five modules. Namespace
`GacelaTest\Feature\ReferenceApp\Invoicing`.

| Module | What it is, and what it shows |
|---|---|
| `Customer` | The customer directory. A `#[Cacheable]` lookup with a per-reference key, and a shape declared with `declareDtoSchema()` whose generated class is committed. |
| `Billing` | Issues invoices. Reaches `Customer` through `#[Provides]` + `getProvidedDependency()`, and announces `Billing\Event\InvoiceIssuedEvent` through the injected event dispatcher without naming whoever reacts; runs a plugin stack of tax rules and a tagged set of validators; reads typed configuration against a declared schema. |
| `Payment` | Takes the money. Declares a fifth resolvable kind, `Gateway`; dispatches by key through a handler registry; gets a stricter retry policy than the rest of the application through a contextual binding. Its four pillars carry the names it arrived with — `PaymentApi`, `PaymentBuilder`, `PaymentSettings`, `PaymentDependencyProvider` — which is what `addSuffixTypeFacade()` and its three siblings are for. |
| `Notification` | Delivers, and reacts. It handles Billing's `InvoiceIssuedEvent` — the subscriber names the event, the publisher names nobody — behind a plugin stack of channels, a header list the application extends with `extendService()`, and the resolver-event listener registered in `gacela.php`. |
| `Reporting` | Reads. Billing's declared shapes through `#[Provides]` and Customer's names through a `#[ServiceMap]` accessor, and nothing else — the module the boundary rules are written about. |

Beside them, `Shared/` is a shared kernel rather than a module: a clock the host
supplies, a retry policy, the invokables that extend the configuration, and the
plugins that run at bootstrap. Both analyser configurations name it as such.

### The two installed packages

[`Packages/`](../tests/Feature/ReferenceApp/Packages) holds two Composer
packages, declared in
[`Invoicing/vendor/composer/installed.json`](../tests/Feature/ReferenceApp/Invoicing/vendor/composer/installed.json)
— hand-written, like the `composer.json` beside the application, because nothing
here is actually installed.

`gacela-fixture/invoice-audit` is **kept**. It adds a delivery channel to the
stack `Notification` publishes and a reaction to the `InvoiceIssuedEvent` that
`Billing` announces, and `gacela.php` names it nowhere: `InvoicingFlowTest` sees
`audit:` receipts beside the `email:` ones, and `debug:events` reports *two*
listeners on `InvoiceIssuedEvent` — a listener that arrived with a package is a
listener like any other, which is the whole point of the report.

`gacela-fixture/legacy-numbering` is **refused**, with the one line that decides
it: `dontDiscover(['gacela-fixture/legacy-numbering'])`. It would replace the
invoice number format `BillingProvider` decides, so `ACME-INV-01001` in the flow
test is the assertion that its file was never opened. A
[discovered config runs arbitrary code at bootstrap](packages.md#read-the-security-note-first),
and this is what saying no to one looks like.

Repositories are in-memory arrays. There is no HTTP and no database: those are
the host's, and the point here is the wiring.

### Configuration

- [`gacela.php`](../tests/Feature/ReferenceApp/Invoicing/gacela.php) — the
  composition root, and the most useful single file to read.
- [`gacela-prod.php`](../tests/Feature/ReferenceApp/Invoicing/gacela-prod.php) —
  only the differences, read when `APP_ENV=prod`.
- `config/app.php`, `config/app-prod.php`, `config/app-prod-eu.php` — the base
  layer and the two that refine it, the second selected by the declared
  `APP_REGION` dimension.
- [`services.php`](../tests/Feature/ReferenceApp/Invoicing/services.php) — the
  wiring that is data, read by `loadDefinitions()`.
- [`module-rules.json`](../tests/Feature/ReferenceApp/Invoicing/module-rules.json)
  — the boundaries, read by `debug:graph --check --rules` and by both analysers.

`config/app.php` names only the keys that mean something in every environment.
`payment.default_method` is set in `config/app-prod.php` and nowhere else, so
outside production the schema's declared default answers for it — the
demonstration that the base layer
[excludes the environment files](config-schema.md#which-files-the-keys-come-from)
`addAppConfig('config/*.php')` also matches. Before it did, that key reached a
developer's machine and invoices settled by SEPA on a laptop.

## The harness

Three test classes, each answering a different question.

| | Asks |
|---|---|
| [`InvoicingFlowTest`](../tests/Feature/ReferenceApp/InvoicingFlowTest.php) | Does the application work? One flow — register, issue, pay, report — run as a developer runs it and again as production in the EU region. Every assertion is a number or a string the application produced. |
| [`InvoicingToolingTest`](../tests/Feature/ReferenceApp/InvoicingToolingTest.php) | Does the toolchain work on it? Every command Gacela ships, run against this application, asserting the exit code and one fact of the output. |
| [`ReferenceAppUsesEveryFeatureTest`](../tests/Feature/ReferenceApp/ReferenceAppUsesEveryFeatureTest.php) | Is it still a reference? Reflects `GacelaConfig`, the attributes, the traits and the command catalogue, and fails on anything the application does not use and has not explained. |

```bash
composer test-feature -- --filter=ReferenceApp
```

Static analysis is the exception, and runs from the integration suite because
both analysers are subprocesses:

```bash
composer test-integration -- --filter=ReferenceAppTest
```

`GacelaTest\Integration\PHPStan\ReferenceAppTest` and its Psalm twin analyse the
application at level `max` / `errorLevel="1"` with the shipped rules **and the
three that are opt-in** — cross-module access, declared module dependencies, and
`#[ServiceMap]` completeness. Their configurations are
[`phpstan-reference-app.neon`](../tests/Feature/ReferenceApp/phpstan-reference-app.neon)
and
[`psalm-reference-app.xml`](../tests/Feature/ReferenceApp/psalm-reference-app.xml),
and both are worth copying into a project.

## Sparring with a new feature

The application exists to be the place a feature is tried before its API is
fixed. The loop:

1. Find where the feature belongs. Not "which module has room" — which module
   has the problem the feature solves. If there is no such module, that is the
   first finding.
2. Use it, and let `ReferenceAppUsesEveryFeatureTest` stop reminding you.
3. Run the three test classes and both analysers. A feature that needs a
   docblock to be usable, an error message that names the wrong file, a `doctor`
   check that reports working code — all of it surfaces here rather than in an
   issue six weeks later.
4. Write down every surprise. Three framework bugs came out of building this
   application the first time: `doctor` reported every `#[Provides]` method that
   takes a `Container` — the shape the attribute's own documentation shows;
   `setAppModulePaths()` written in `gacela.php` was silently dropped by the
   setup merger, so every command scanned the whole application root; and a
   second bootstrap in one process resolved its pillars against the *previous*
   application's bindings, because the resolver memoized the merged `gacela.php`
   on an object that outlives the bootstrap.

## Two things it does not prove

The module graph is built from `use` imports at module granularity, so
`module-rules.json` can say that nothing may depend on `Reporting` and cannot
say that Reporting may reach only Billing's *facade*. That second rule is the
analysers' job, and it is why `CrossModuleViaFacadeRule` and its method-call half
are both enabled in the configurations above.

It also cannot say anything about `Notification` reacting to `Billing`. An event
leaves no import behind in the module that dispatched it, so no graph and no rule
can tell you who is listening — `debug:events` can, which is the report that
answers it, and the registration in `gacela.php` is the one place it is written
down.

And the two generated shapes are excluded from both analysers. `dto:generate`
writes a `@psalm-suppress` for the one thing Psalm objects to and nothing for
PHPStan, and the file's own header says not to edit it — so the only place that
can be answered is the analyser configuration, scoped to the two files it is
true of.

## See also

- [Getting started](getting-started.md) — the same ideas, one module at a time
- [CLI commands](cli.md) — what each of the commands the harness runs is for
- [Module boundaries](module-boundaries.md) — the rules file and the graph gate
- [Static analysis](static-analysis.md) — every rule the configurations above turn on
