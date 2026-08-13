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

$events = $this->recordedGacelaEvents();                              // all of them
$resolved = $this->recordedGacelaEventsOf(ServiceResolvedEvent::class); // one type
```

If you only need the reset helpers inside an existing test hierarchy, use the [`ContainerFixture`](../src/Framework/Testing/ContainerFixture.php) trait directly — `GacelaTestCase` builds on it.

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

## Scaffolding a testable module

`make:module` can scaffold a module already wired for testing:

```bash
vendor/bin/gacela make:module App/Greeting --template=service --with-tests
```

The `service` template generates the four pillars plus a `Domain` service the Facade delegates to, and `--with-tests` adds a ready-to-run facade test (a `GacelaTestCase`) under the module's `Tests/` directory.
