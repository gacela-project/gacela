# Testing

Gacela ships testing utilities under `Gacela\Framework\Testing` so module tests need near-zero boilerplate. They require `phpunit/phpunit`, which gacela only *suggests* — it never becomes a runtime dependency of your application.

## GacelaTestCase

Extend `GacelaTestCase` instead of PHPUnit's `TestCase` when a test bootstraps a Gacela application:

```php
use Gacela\Framework\Testing\GacelaTestCase;

final class CheckoutTest extends GacelaTestCase
{
    public function test_facade_resolves_payment_gateway(): void
    {
        $this->bootstrapGacelaWithConfig(__DIR__, ['retries' => 3]);

        (new CheckoutFacade())->pay();

        $this->assertServiceResolved(PaymentGateway::class);
    }
}
```

What it gives you:

- **Isolation for free.** Every `bootstrapGacela()` starts from a clean in-memory state, and `tearDown()` drops all Gacela singletons — no more `Gacela::resetCache()` / `Config::resetInstance()` boilerplate, and a test can safely bootstrap twice in one process.
- **Config overrides.** `bootstrapGacelaWithConfig($dir, ['key' => 'value'])` is a shortcut for the most common override; `bootstrapGacela($dir, $configFn)` accepts the usual `GacelaConfig` closure for everything else.
- **Lifecycle-event recording.** Each bootstrap registers a generic listener, so the [framework lifecycle events](events.md) become assertable:

```php
$this->assertServiceResolved('checkout-gateway');       // ServiceResolvedEvent seen
$this->assertBindingRegistered(PaymentGateway::class);  // BindingRegisteredEvent seen
$this->assertEventDispatched(InvoiceIssued::class);     // any event, yours included

$events = $this->recordedGacelaEvents();                              // all of them
$resolved = $this->recordedGacelaEventsOf(ServiceResolvedEvent::class); // one type
```

`assertEventDispatched()` and `recordedGacelaEventsOf()` answer about [your own events](events.md#your-own-events) as readily as about the framework's — one dispatcher, one recording — so a test of a module that announces something needs no listener of its own. Both match by inheritance, the way `registerSpecificListener()` does, so naming a base class or `GacelaEventInterface::class` asks about the whole family. To assert on a payload, read the events: `recordedGacelaEventsOf()` returns them typed and in dispatch order.

If you only need the reset helpers inside an existing test hierarchy, use the [`ContainerFixture`](../src/Framework/Testing/ContainerFixture.php) trait directly — `GacelaTestCase` builds on it.

### What else the trait gives you

Beyond the resets and the `swapModule*()` calls below:

```php
$dir = $this->containerTempDir();   // a new temp dir, removed when the process ends
```

**One directory per call**, not one per test — two calls hand back two paths. Hold the return value rather than calling it again to name the same place twice. Nothing needs cleaning up by hand; `cleanupContainerTempDirs()` exists for when you want it to happen between methods rather than at process end.

```php
$snapshot = $this->captureContainerState();
// ... a test that bootstraps differently, rebinds, or rewrites config
$this->restoreContainerState($snapshot);
```

The snapshot covers the in-memory class-name cache, the config values, the app root and the cache directory. It deliberately does **not** capture resolved service instances — those can hold file handles and connections — so restoring rebuilds them lazily rather than handing back objects that outlived their resources.

### Bootstrapping twice without either of them

Neither is required. But calling `Gacela::bootstrap()` a second time in one process on your own keeps the **previous** boot's services, and says nothing:

```php
Gacela::bootstrap($dir, static fn (GacelaConfig $c) => $c->addBinding(Clock::class, $frozenClock));
Gacela::get(Clock::class);   // the frozen one, as expected

Gacela::bootstrap($dir, static fn (GacelaConfig $c) => $c->addBinding(Clock::class, $realClock));
Gacela::get(Clock::class);   // still the frozen one
```

The configuration *is* re-read, so the second boot leaves you with new config and old services. Add [`resetInMemoryCache()`](../src/Framework/Bootstrap/GacelaConfig.php) to the closure and both move together:

```php
Gacela::bootstrap($dir, static function (GacelaConfig $config): void {
    $config->resetInMemoryCache();
    // …
});
```

`GacelaTestCase` and both framework bridges call it on every boot for exactly this reason. It is opt-in rather than automatic because a process that re-bootstraps with the *same* wiring pays to resolve everything again.

## Testing one module: `bootstrapModule()`

The everyday test of a modular application is one module with its neighbours replaced. That is one call:

```php
$this->bootstrapModule(__DIR__, InvoiceFacade::class, doubles: [
    BillingFacade::class => $this->createStub(BillingFacade::class),
    PaymentGatewayInterface::class => new FakeGateway(),
]);

$invoice = (new InvoiceFacade())->issue('acme-nl', 10_000);
```

Two things happen.

**Discovery is narrowed** to the directory the Facade lives in — the slice. `doctor`, `list:modules`, `debug:graph` and the [boundary assertions](#module-boundaries-in-a-test-method) then answer about that module instead of about the whole application. The narrowing is applied after `gacela.php` has been read, so an application that declares its own `setAppModulePaths()` does not silently un-narrow it.

**Each double is applied through the seam that fits it**, so the test does not have to know which of three a given dependency arrives on:

| The double is | It becomes |
|---|---|
| an `AbstractFactory` / `AbstractConfig` / `AbstractProvider` instance | that pillar of the module its key's **Facade** names — the `swapModule*()` calls below |
| any other object, keyed by a class or interface | a container binding, a lazy service, and a resolved-class override — the last is the path a neighbour Facade reached through `getProvidedDependency()` or `#[ServiceMap]` travels |
| a `Closure` or a class-string, keyed by a class or interface | a container binding and a lazy service |
| anything, keyed by a **container id** | a replacement for that id wherever it is registered, including in the module's own Provider — which nothing written at application level can otherwise reach |

The third argument is a `GacelaConfig` closure, composed with the narrowing rather than replacing it, for a slice that still has to configure the application it is a slice of:

```php
$this->bootstrapModule(__DIR__, InvoiceFacade::class,
    doubles: [BillingFacade::class => $billing],
    configFn: static fn (GacelaConfig $config) => $config->addExternalService('clock', $frozenClock),
);
```

Like `bootstrapGacela()`, it bootstraps once per test, and `tearDown()` drops everything it registered.

### A neighbour has to leave its Facade open

A consumer that type-hints a **`final`** Facade cannot be handed a stand-in for it by anyone — not PHPUnit, not a hand-written subclass. A module meant to be replaceable from its neighbours' tests declares its Facade non-`final`; where that is not an option, replace the neighbour's **Factory** instead, which needs nothing from the class it replaces.

### What it checks, and what it cannot

A double registered under a class or interface it is **not an instance of** is refused with a `ModuleDoubleException`, because it would otherwise reach a consumer that type-hints the real one and fail there instead. Naming a module by anything but its Facade is refused for the same reason `swapModuleFactory()` refuses it.

What is **not** checked is whether the module actually depends on what is being doubled. Reflection can see a module's `#[Provides]` ids and return types, its `#[ServiceMap]` accessors and its pillar constructors — and that misses a plugin-stack interface named only as a call argument, an app-wide binding autowired into a nested constructor, and anything a Provider registers from inside a method body. On the [reference application](reference-app.md) those are four of nine legitimate doubles, so such a check would refuse real tests rather than catch typos.

## Replacing another module

Testing module A in isolation means replacing module B. A container binding only works when B's Facade arrives through a Provider, and a consumer that writes `new BlogFacade()` leaves nothing to bind — so the seam is the **Factory** every Facade resolves:

```php
$this->swapModuleFactory(BlogFacade::class, new class() extends AbstractFactory {
    public function createPostReader(): PostReader
    {
        return new InMemoryPostReader(['a post']);
    }
});

(new CheckoutFacade())->summary();  // reaches the double, not the real Blog
```

The double extends `AbstractFactory`, not `BlogFactory`. It only has to carry the methods the Facade under test actually calls — `swapModuleFactory()` takes an `AbstractFactory`, so what it *is* does not have to be Blog's own.

**If `BlogFactory` is `final`, that is the only form available.** A `final` class cannot be subclassed (PHP raises a fatal error) and cannot be doubled by PHPUnit either (`ClassIsFinalException`), so neither `new class() extends BlogFactory` nor `$this->createStub(BlogFactory::class)` compiles or runs. `make:module` generates `final` pillars, and this codebase prefers them, so assume that is the case unless you know otherwise.

Extending the real Factory is worth it when it is *not* final and you want to keep its other `create*()` methods and override one:

```php
$this->swapModuleFactory(BlogFacade::class, new class() extends BlogFactory {   // BlogFactory must not be final
    public function createPostReader(): PostReader
    {
        return new InMemoryPostReader(['a post']);
    }
});
```

- `swapModuleFactory()`, `swapModuleConfig()` and `swapModuleProvider()` all take the **Facade** class: that is the name a consumer already knows, and the one the resolver derives a module's pillars from.
- Any object of the right pillar type works: a standalone `AbstractFactory`, an anonymous subclass of the real one, or a PHPUnit stub — the last two only where the class is not `final`.
- The swap survives repeated resolutions, and applies to a module that was already resolved earlier in the same test.
- Swapping the same module twice keeps the last double.
- Every swap is dropped in `tearDown()`, so the next test sees the real module again whatever order the suite runs in.

Naming a class that is not a Facade — the Factory itself, or a typo — throws a `ModuleDoubleException` rather than registering a double nothing would ever read.

This replaces reaching into `AnonymousGlobal::overrideExistingResolvedClass()`, which needed the resolver's key format and left the Facade's memoised Factory in place.

## Module boundaries in a test method

A boundary decision that lives only in CI configuration is one a module's own tests cannot state. `Gacela\Console\Testing\ModuleAssertions` is a standalone trait, so it goes into whatever base test class a project already has:

```php
use Gacela\Console\Testing\ModuleAssertions;

final class InvoiceBoundaryTest extends TestCase
{
    use ModuleAssertions;

    public function test_invoice_reaches_billing_and_customer_and_nothing_else(): void
    {
        Gacela::bootstrap(__DIR__);

        self::assertModuleDependsOnlyOn(InvoiceFacade::class, [BillingFacade::class, CustomerFacade::class]);
        self::assertNoModuleCycles(__DIR__ . '/allowed-cycles.json');
        self::assertModuleRulesHold(__DIR__ . '/module-rules.json');
    }
}
```

- `assertModuleDependsOnlyOn()` takes the module — any class inside it, its Facade by convention, or its namespace — and the modules it may reach. An allowance may name a namespace covering several modules. Naming a module the running configuration does not scan **fails**, listing the modules it did find, rather than passing on an empty dependency list.
- `assertNoModuleCycles()` reads the same allowed-cycles file `debug:graph --check --allowed-cycles` reads, and is as self-invalidating: an allowance whose cycle has since been broken fails too.
- `assertModuleRulesHold()` reads the same [`module-rules.json`](module-boundaries.md) the CLI and the PHPStan/Psalm rules read. One file, enforced from wherever you happen to be looking.

Every failure names the offending edge **and the `use` statement behind it**, as `file:line`:

```
"App\Invoice" may depend only on:
  - App\Customer

✗ App\Invoice -> App\Billing
    /app/src/Invoice/Domain/InvoiceIssuer.php:12  use App\Billing\BillingFacade;
```

The line is where the `use` statement opens, so every name of a grouped import reports the same one. A dependency that arrives without an import — a fully qualified name written inline, a class-string in configuration — has no evidence to show, because the graph does not see it either: nothing is reported that the check did not find.

The application must be bootstrapped, since these read the modules the running configuration declares. That is also what makes `bootstrapModule()`'s narrowing meaningful here: inside a slice, these assertions answer about one module.

**These live in the `Gacela\Console` namespace, not on `GacelaTestCase`.** The module graph is built by scanning source files, which is console work, and `Gacela\Framework` references `Gacela\Console` in no file. A framework that sells module boundaries must not invert its own to ship a test helper — so `bootstrapModule()`, which needs only framework primitives, stayed on `GacelaTestCase`, and this came here. A test class writes `use ModuleAssertions;` and has both.

## Scaffolding a testable module

`make:module` can scaffold a module already wired for testing:

```bash
vendor/bin/gacela make:module App/Greeting --template=service --with-tests
```

The `service` template generates the four pillars plus a `Domain` service the Facade delegates to, and `--with-tests` adds a ready-to-run facade test (a `GacelaTestCase`) under the module's `Tests/` directory.
