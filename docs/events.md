# Events

Gacela dispatches domain events while it works: bootstrapping, reading config, resolving classes, wiring providers, touching caches. Listening to them is the best window into "how does Gacela resolve my modules" — useful for debugging, profiling, tracing, and CI guards, without patching the framework.

## Dispatch model

- Every event is a small immutable class implementing `GacelaEventInterface` (one `toString()` method) — the framework's, and [your own](#your-own-events).
- By default nothing listens: the dispatcher is a `NullEventDispatcher`, and every dispatch site is guarded by `EventDispatcherInterface::hasListeners()`, so **no event object is even allocated** unless a listener is registered for it. Events are zero-cost when unused, including on the class-resolution hot path.
- Registering any listener switches to a `ConfigurableEventDispatcher`. Listeners are plain callables receiving the event object; they are notify-only (events are immutable, there is no propagation stopping).
- A specific listener matches **by inheritance**: it runs for the class it names and for every event that extends or implements it. `AbstractGacelaClassResolverEvent::class` covers all four resolver events, and `GacelaEventInterface::class` covers every event there is — the typed way to listen to everything. The applicable listeners are worked out **once per concrete event class** and kept, so past the first dispatch of a class the guard is a single array lookup, exactly as when matching was on the exact class.
- A listener that throws propagates. Nothing catches it, so a logging listener that blows up takes the resolve down with it — which is the right default for a framework (swallowing would hide the bug), but it means listener bodies belong in a `try` if the work in them can fail.
- `GacelaConfig::disableEventListeners()` turns the whole mechanism off regardless of what was registered — the dispatcher is never built, so registered listeners silently do not run. That is the point in production, and the first thing to check when a listener appears dead. `vendor/bin/gacela doctor` reports the combination, so you do not have to remember to look.

Two kinds of listeners:

```php
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Gacela;

Gacela::bootstrap($appRootDir, static function (GacelaConfig $config): void {
    // Generic: receives every event
    $config->registerGenericListener(static function (GacelaEventInterface $event): void {
        error_log($event->toString());
    });

    // Specific: receives that event class and everything below it
    $config->registerSpecificListener(
        ResolvedClassCreatedEvent::class,
        static function (ResolvedClassCreatedEvent $event): void {
            error_log('created: ' . $event->classInfo()->getCacheKey());
        },
    );
});
```

A generic listener makes *every* dispatch site allocate its event, including hot paths — prefer specific listeners in production, and name the narrowest type that covers what you want. `AbstractGacelaClassResolverEvent::class` costs you the four resolver events and nothing else; a generic listener costs you every event in the catalog below.

`registerSpecificListener(GacelaEventInterface::class, …)` is the typed way to listen to everything: same reach and same cost as a generic listener, but the callable is declared against a type, so the parameter you write is checked instead of silently never matching.

## Two sources, one set of listeners

Listeners can be registered in the `Gacela::bootstrap()` closure and in `gacela.php` (and in `gacela-{APP_ENV}.php`). Both contribute: the closure is the base and the files merge onto it, so nothing replaces anything.

- Every registration runs, exactly once. Registering the same callable in both places registers it twice, and it runs twice — nothing deduplicates them.
- Order is the order of declaration, with the closure's first. Generic listeners run before specific ones for the same event.
- The merged set is what `doctor` and `debug:events` report. They read the configuration rather than the dispatcher, so a listener declared in `gacela.php` shows up there like any other — which is what makes `debug:events` usable for answering "is my listener registered".

## Lifecycle ordering

```
Gacela::bootstrap()
 ├─ GacelaBootstrapStartedEvent
 ├─ PackageConfigMergedEvent         (per discovered package, in merge order)
 ├─ ReadPhpConfigEvent               (per config file)
 ├─ ConfigInitializedEvent
 └─ GacelaBootstrapFinishedEvent

first Facade/Factory/Config access (per module)
 ├─ ClassName*Event                  (find the class name; cached vs candidates)
 ├─ ResolvedClass*Event              (created / cached / parent / default)
 ├─ BindingRegisteredEvent           (per configured binding, on container build)
 ├─ ProviderRegisteredEvent          (module provider wired)
 └─ ServiceResolvedEvent             (per service, first `get()` on the container)
```

## Your own events

The dispatcher is not the framework's private property: a module can dispatch its own events through it, which is how one module reacts to another without depending on it. `InvoiceIssued` → Notification sends the mail, Reporting updates a projection, and Billing knows about none of them.

Three pieces, and nothing else:

**1. The event is a class implementing `GacelaEventInterface`.** One `toString()` method, immutable, carrying facts rather than a handle onto the module that raised it.

```php
namespace App\Billing\Event;

use Gacela\Framework\Event\GacelaEventInterface;

final class InvoiceIssued implements GacelaEventInterface
{
    public function __construct(
        private readonly string $invoiceNumber,
        private readonly string $customerName,
    ) {
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function customerName(): string
    {
        return $this->customerName;
    }

    public function toString(): string
    {
        return 'Invoice issued: ' . $this->invoiceNumber;
    }
}
```

**2. The dispatcher is an ordinary dependency.** A Factory asks for it by interface, like a repository or a clock — there is no trait to mix in and no static call to make:

```php
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

final class BillingFactory extends AbstractFactory
{
    public function createInvoiceIssuer(): InvoiceIssuer
    {
        return new InvoiceIssuer($this->getEventDispatcher());
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->getProvidedDependency(EventDispatcherInterface::class);
    }
}
```

Whatever `setEventDispatcher()` installed is what arrives — including a host framework's own bus. The id is a default rather than a reservation: an application that binds something else under `EventDispatcherInterface::class`, app-wide or in one module's Provider, gets what it declared.

Then dispatch, behind the same guard the framework uses on its own dispatch sites:

```php
if ($this->events->hasListeners(InvoiceIssued::class)) {
    $this->events->dispatch(new InvoiceIssued($number, $customerName));
}
```

The guard is worth writing. With nothing listening the event object is never constructed, so an announcement costs a single array lookup — and a module keeps that property whether or not this deployment cares about the event.

**3. The listener is registered where every listener is:** in the `Gacela::bootstrap()` closure or in `gacela.php`.

```php
use App\Billing\Event\InvoiceIssued;
use App\Notification\NotificationFacade;
use Gacela\Framework\Gacela;

$config->registerSpecificListener(
    InvoiceIssued::class,
    static function (InvoiceIssued $event): void {
        /** @var NotificationFacade $notifications */
        $notifications = Gacela::getRequired(NotificationFacade::class);
        $notifications->onInvoiceIssued($event);
    },
);
```

That line is the only place both modules are named, and `gacela.php` is the composition root — the one file allowed to know both sides. Resolve the facade through the locator rather than constructing it, so a test that replaces the module also replaces what the listener reaches.

What this buys, and what it costs:

- **The publisher names nobody.** `debug:graph --check --rules` draws no edge from Billing to Notification and the analysers' cross-module rules see nothing to complain about, because there is no import to see. A second reaction is another registration and no change to Billing.
- **The subscriber names the event.** It has to: it type-hints it. Put the event in the module's `Event` sub-namespace — `App\Billing\Event\InvoiceIssued` — which is one of the namespaces a module publishes by convention, so a subscriber may name it while the rest of `App\Billing` stays private. See [module boundaries](module-boundaries.md).
- **Nothing draws the wiring for you.** An event leaves no import behind, so no graph can tell you who listens. `debug:events` can, and it is the report to reach for.
- **A Facade only delegates**, so reading an event is the Factory's job — the shipped `gacela.facadeOnlyDelegates` rule holds a project to that.

Everything the framework's own events get applies unchanged: the typed `registerSpecificListener()`, matching by inheritance (a project's own base event covers the family below it, `GacelaEventInterface::class` covers everything), the per-class listener memo, and the `hasListeners()` guard.

In tests, `GacelaTestCase::assertEventDispatched()` answers from the recording `bootstrapGacela()` installs, for a project's events as readily as for Gacela's — see [testing](testing.md).

The [reference application](reference-app.md) does exactly this: `Billing` announces `InvoiceIssuedEvent`, `Notification` handles it, and a test asserts that Billing depends on `Customer` and nothing else.

## Seeing what listens

```bash
vendor/bin/gacela debug:events
```

The same catalog as below, read off the classes rather than this page, with the listeners *your* project registered against each one. Because a specific listener matches by inheritance, an event can be covered by a registration that never names it — the listener column names the target that does, so one listener on `AbstractGacelaClassResolverEvent` reads as four covered events rather than four mysteries.

**Your own events are listed too**, under their own namespace and marked `project`. They are found under the paths module discovery walks — `appModulePaths`, or the application root — by implementing `GacelaEventInterface` or being named `*Event`, and narrowed to `setProjectNamespaces()` when you declared it. Nothing in `vendor/` is scanned. An event of yours that is *missing* from the listing is one no listener registration can be checked against, and the fix is usually the interface: a class that forgot `implements GacelaEventInterface` looks exactly like an event, is accepted by `registerSpecificListener()`, and never fires. `doctor` reports that target as one nothing can ever match.

`--listened` narrows to the events something watches, an optional argument narrows by class name — `debug:events App\` for your own, if they share a prefix — and `--json` reports the same thing as a document, with a `source` field per event and a `projectEvents` count in the summary. It also says when `disableEventListeners()` is in effect, which is the state in which everything below is registered and none of it runs, and when a dispatcher was supplied through `setEventDispatcher()` — the listener table is what the *configuration* registered, and a supplied dispatcher carries every event on to a bus this command cannot see into.

Where `doctor` answers "is this listener target a thing an event can be", this answers "what does the framework offer, what do I offer, and what am I actually watching".

## Event catalog

All classes live under `Gacela\Framework\Event\`. "Hot path" marks events fired on every warm resolve — with only unrelated listeners registered they still cost nothing.

### Bootstrap (`Event\Bootstrap`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `GacelaBootstrapStartedEvent` | `Gacela::bootstrap()` begins (after the setup is processed) | `appRootDir()` | no |
| `PackageConfigMergedEvent` | an installed package's `extra.gacela.config` was read and merged — one per package, in merge order | `packageName()`, `configFile()`, `position()` | no |
| `GacelaBootstrapFinishedEvent` | bootstrap completed | `durationMs()` | no |

### Config (`Event\Config`, `Event\ConfigReader`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `ConfigInitializedEvent` | merged config built by `Config::init()` | `keyCount()` | no |
| `ConfigKeyReadEvent` | every `Config::get()` | `key()` | **yes** |
| `ConfigKeyNotFoundEvent` | `Config::get()` misses (default returned or exception thrown) | `key()` | no |
| `ConfigReader\ReadPhpConfigEvent` | a PHP config file is read | `absolutePath()` | no |

### Cacheable methods (`Event\Attribute`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `CacheableHitEvent` | a `#[Cacheable]` method is answered from storage | `className()`, `method()`, `cacheKey()` | **yes** |
| `CacheableMissEvent` | a `#[Cacheable]` method runs its callback | `className()`, `method()`, `cacheKey()`, `computeNanoseconds()`, `computeMilliseconds()`, `ttl()` | **yes** |

Together these make a hit rate measurable, which is the question worth asking of any cache. It is also the one that catches the default-storage trap in production: `InMemoryCacheStorage` dies with the process, so under PHP-FPM a method annotated for an hour's TTL is recomputed on every request — every call reports a miss, and nothing else says so. `doctor` reports the same thing from the configuration, before a request is served.

The duration is carried in nanoseconds as `hrtime()` reported it, with `computeMilliseconds()` converting; it times the callback alone, not the storage write: what a hit saves you is the callback, and a backend's own latency is the backend's to report.

Both are guarded like every other dispatch, and the guard is what makes them free — `CacheableBench` is unchanged with nothing listening (0.732μs, identical to before they existed).

### Class resolution (`Event\ClassResolver`)

All four resolver events extend `AbstractGacelaClassResolverEvent` and expose `classInfo()`.

| Event | Fires when | Hot path |
|---|---|---|
| `ResolvedClassCachedEvent` | resolve served from the in-memory instance cache | **yes** |
| `ResolvedClassCreatedEvent` | a new instance was created for the caller | no |
| `ResolvedClassTriedFromParentEvent` | resolution retried with the caller's parent class | no |
| `ResolvedCreatedDefaultClassEvent` | no class found; default (e.g. anonymous config) used | no |

### Class-name finding (`Event\ClassResolver\ClassNameFinder`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `ClassNameCachedFoundEvent` | class name served from cache | `cacheKey()`, `className()` | **yes** |
| `ClassNameValidCandidateFoundEvent` | a candidate class name exists | `className()` | no |
| `ClassNameInvalidCandidateFoundEvent` | a candidate class name does not exist | `className()` | no |
| `ClassNameNotFoundEvent` | no candidate matched | `classInfo()`, `resolvableTypes()` | no |

### Resolver caches (`Event\ClassResolver\Cache`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `ClassNameCacheCachedEvent` | class-name cache instance reused | — | **yes** |
| `ClassNamePhpCacheCreatedEvent` | file-backed class-name cache created | `cacheDir()` | no |
| `ClassNameInMemoryCacheCreatedEvent` | in-memory class-name cache created | — | no |
| `CustomServicesCacheCachedEvent` | custom-services cache instance reused | — | **yes** |
| `CustomServicesPhpCacheCreatedEvent` | file-backed custom-services cache created | — | no |
| `CustomServicesInMemoryCacheCreatedEvent` | in-memory custom-services cache created | — | no |

### Container (`Event\Container`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `ServiceResolvedEvent` | first `get()` of a service id on a container | `id()` | **yes** |
| `BindingRegisteredEvent` | a binding/factory/alias/contextual binding is registered on container build | `id()` | no |

### Provider (`Event\Provider`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `ProviderRegisteredEvent` | a module's provider wired its dependencies | `providerClass()`, `moduleName()` | no |

### Cache files (`Event\Cache`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `CacheClearedEvent` | a Gacela cache file is deleted | `cacheFile()` | no |
| `CacheWarmedEvent` | `vendor/bin/gacela cache:warm` finished | `moduleCount()`, `failedCount()`, `skippedCount()` | no |

`failedCount()` and `skippedCount()` are not the same number. A pillar class a module declares but does not have is *skipped* — a normal shape, not an error. A pillar that is there and blows up on resolution is *failed*. Alert on `failedCount()`.

## Cookbook

### Log every resolved class

```php
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;

$config->registerSpecificListener(
    AbstractGacelaClassResolverEvent::class,
    static function (AbstractGacelaClassResolverEvent $event): void {
        error_log($event->toString());
    },
);
```

The abstract parent covers all four resolver events, and nothing else pays for it: every other dispatch site still skips allocating its event. A generic listener with an `instanceof` filter reaches the same four and allocates all the rest to throw them away.

### Time the bootstrap

```php
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;

$config->registerSpecificListener(
    GacelaBootstrapFinishedEvent::class,
    static function (GacelaBootstrapFinishedEvent $event): void {
        error_log(sprintf('gacela booted in %.2fms', $event->durationMs()));
    },
);
```

### Fail CI on unresolved classes

```php
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;

$config->registerSpecificListener(
    ClassNameNotFoundEvent::class,
    static function (ClassNameNotFoundEvent $event): void {
        throw new RuntimeException('Unresolvable gacela class: ' . $event->toString());
    },
);
```

### Trace config key reads

```php
use Gacela\Framework\Event\Config\ConfigKeyReadEvent;

/** @var array<string,int> $reads */
$reads = [];
$config->registerSpecificListener(
    ConfigKeyReadEvent::class,
    static function (ConfigKeyReadEvent $event) use (&$reads): void {
        $reads[$event->key()] = ($reads[$event->key()] ?? 0) + 1;
    },
);
```

### Export a resolution timeline (profiler / OpenTelemetry)

```php
use Gacela\Framework\Event\GacelaEventInterface;

/** @var list<array{t: float, event: string}> $timeline */
$timeline = [];
$config->registerGenericListener(static function (GacelaEventInterface $event) use (&$timeline): void {
    $timeline[] = ['t' => microtime(true), 'event' => $event->toString()];
});

// Later: convert each entry into a span/annotation for your tracer, e.g.
// $span->addEvent($entry['event'], ['timestamp' => $entry['t']]);
```

## Custom dispatchers

`SetupGacela::setEventDispatcher()` accepts any `EventDispatcherInterface`. Implementations must provide `dispatch(object $event): void` **and** `hasListeners(string $eventClass): bool` — return `false` from `hasListeners()` for event classes you don't care about and the framework will skip allocating them entirely.

Set it from the bootstrap closure, the same place everything else is configured:

```php
Gacela::bootstrap($appRootDir, static function (GacelaConfig $config): void {
    $config->setEventDispatcher(new MyDispatcher($myBus));
});
```

### PSR-14, for a hosted application

A Symfony or Laravel application already has a bus, and one event system per application is the point — so `setEventDispatcher()` also accepts a `Psr\EventDispatcher\EventDispatcherInterface` and wraps it for you. No adapter to write:

```php
use Gacela\Framework\Bootstrap\GacelaConfig;
use Symfony\Component\EventDispatcher\EventDispatcher;

Gacela::bootstrap($appRootDir, static function (GacelaConfig $config) use ($dispatcher): void {
    // Symfony's, Laravel's, or any other PSR-14 implementation.
    $config->setEventDispatcher($dispatcher);
});
```

Gacela's events then arrive on the host's bus, the listeners you registered beside it still run (see below), and your own events go to the same place — nothing is dispatched twice and there is no second event system beside the first.

**One thing to know about the cost.** PSR-14 offers no way to ask what is subscribed, so the adapter's `hasListeners()` answers `true` for everything: every guarded dispatch site allocates its event and hands it over, including the ones on the class-resolution hot path. That is the price of routing events to a bus that cannot be asked, and it is paid only by an application that installed one — supply no dispatcher and the guard is the single array lookup it has always been. If you want a narrower answer, implement Gacela's `EventDispatcherInterface` yourself and say so in `hasListeners()`; that is exactly what this section's first paragraph is for.

The other direction is a class rather than a parameter: a library that type-hints PSR-14 can be handed the dispatcher this application configured, listeners and all.

```php
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\Psr14EventDispatcher;

final class ReportingFactory extends AbstractFactory
{
    public function createLibrary(): SomeLibrary
    {
        return new SomeLibrary(new Psr14EventDispatcher(
            $this->getProvidedDependency(EventDispatcherInterface::class),
        ));
    }
}
```

Outside a module, `Config::getInstance()->getSetupGacela()->getEventDispatcher()` is the same object.

It returns the event, as PSR-14 requires, and honours both halves of the contract Gacela's own dispatch sites honour: a dispatcher that declines the class in `hasListeners()` is not told, and an event that arrives already stopped is not dispatched. Gacela's listeners are notify-only, so propagation cannot be stopped *between* them.

`ConfigurableEventDispatcher` deliberately does not implement PSR-14 itself. Its `dispatch()` returns `void`, which satisfies the signature — PSR-14 declares no return type — while breaking the contract, which says the event comes back. A class that is a PSR-14 dispatcher in name and returns nothing is worse than one that honestly is not, so the conversion is explicit.

A dispatcher you supply **takes precedence over `disableEventListeners()`**: that switch governs the dispatcher Gacela would *build*, and this is one it does not build. To go quiet, return `false` from `hasListeners()` — which also skips allocating the events, where the switch would only stop them being delivered.

### What it composes with

`setEventDispatcher()` says what *delivers* the events; `registerGenericListener()` and `registerSpecificListener()` say what must *run*. They answer different questions, so supplying a dispatcher does not cancel the listeners registered beside it — in the same closure, or in `gacela.php`. Both happen:

1. The configured listeners run first, in registration order.
2. Then the event is offered to the dispatcher you supplied, which receives it if its `hasListeners()` says yes.

So `hasListeners()` on the composition is true when *either* side would act, and returning `false` from yours still means yours is not told — a configured listener interested in the same event does not smuggle it through to you.

With no listeners registered anywhere, the dispatcher you supply *is* the dispatcher: nothing is composed, and it is the object every dispatch site talks to.

Handing back a dispatcher Gacela built for the same setup (`$setup->setEventDispatcher($setup->getEventDispatcher())`) is a no-op rather than a composition — otherwise its own listeners would be delivered twice.

