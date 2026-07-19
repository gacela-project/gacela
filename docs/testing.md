# Testing

Gacela ships testing utilities under `Gacela\Framework\Testing` so module tests
need near-zero boilerplate. They require `phpunit/phpunit` (a dev dependency of
your project, not of gacela).

## GacelaTestCase

Extend `GacelaTestCase` instead of PHPUnit's `TestCase` when a test bootstraps
a Gacela application:

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

- **Isolation for free.** Every `bootstrapGacela()` starts from a clean
  in-memory state, and `tearDown()` drops all Gacela singletons — no more
  `Gacela::resetCache()` / `Config::resetInstance()` boilerplate, and a test
  can safely bootstrap twice in one process.
- **Config overrides.** `bootstrapGacelaWithConfig($dir, ['key' => 'value'])`
  is a shortcut for the most common override; `bootstrapGacela($dir, $configFn)`
  accepts the usual `GacelaConfig` closure for everything else.
- **Lifecycle-event recording.** Each bootstrap registers a generic listener,
  so the [framework lifecycle events](events.md) become assertable:

```php
$this->assertServiceResolved('checkout-gateway');       // ServiceResolvedEvent seen
$this->assertBindingRegistered(PaymentGateway::class);  // BindingRegisteredEvent seen

$events = $this->recordedGacelaEvents();                              // all of them
$resolved = $this->recordedGacelaEventsOf(ServiceResolvedEvent::class); // one type
```

If you only need the reset helpers inside an existing test hierarchy, use the
[`ContainerFixture`](../src/Framework/Testing/ContainerFixture.php) trait
directly — `GacelaTestCase` builds on it.

## Scaffolding a testable module

`make:module` can scaffold a module already wired for testing:

```bash
vendor/bin/gacela make:module App/Greeting --template=service --with-tests
```

The `service` template generates the four pillars plus a `Domain` service the
Facade delegates to, and `--with-tests` adds a ready-to-run facade test (a
`GacelaTestCase`) under the module's `Tests/` directory.
