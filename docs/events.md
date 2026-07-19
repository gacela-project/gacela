# Events

Gacela dispatches domain events while it works: bootstrapping, reading config, resolving classes,
wiring providers, touching caches. Listening to them is the best window into "how does Gacela
resolve my modules" — useful for debugging, profiling, tracing, and CI guards, without patching
the framework.

## Dispatch model

- Every event is a small immutable class implementing `GacelaEventInterface` (one `toString()` method).
- By default nothing listens: the dispatcher is a `NullEventDispatcher`, and every dispatch site is
  guarded by `EventDispatcherInterface::hasListeners()`, so **no event object is even allocated**
  unless a listener is registered for it. Events are zero-cost when unused, including on the
  class-resolution hot path.
- Registering any listener switches to a `ConfigurableEventDispatcher`. Listeners are plain
  callables receiving the event object; they are notify-only (events are immutable, there is no
  propagation stopping).

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

    // Specific: receives only that event class
    $config->registerSpecificListener(
        ResolvedClassCreatedEvent::class,
        static function (ResolvedClassCreatedEvent $event): void {
            error_log('created: ' . $event->classInfo()->getCacheKey());
        },
    );
});
```

A generic listener makes *every* dispatch site allocate its event, including hot paths — prefer
specific listeners in production.

## Lifecycle ordering

```
Gacela::bootstrap()
 ├─ GacelaBootstrapStartedEvent
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

## Event catalog

All classes live under `Gacela\Framework\Event\`. "Hot path" marks events fired on every warm
resolve — with only unrelated listeners registered they still cost nothing.

### Bootstrap (`Event\Bootstrap`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `GacelaBootstrapStartedEvent` | `Gacela::bootstrap()` begins (after the setup is processed) | `appRootDir()` | no |
| `GacelaBootstrapFinishedEvent` | bootstrap completed | `durationMs()` | no |

### Config (`Event\Config`, `Event\ConfigReader`)

| Event | Fires when | Payload | Hot path |
|---|---|---|---|
| `ConfigInitializedEvent` | merged config built by `Config::init()` | `keyCount()` | no |
| `ConfigKeyReadEvent` | every `Config::get()` | `key()` | **yes** |
| `ConfigKeyNotFoundEvent` | `Config::get()` misses (default returned or exception thrown) | `key()` | no |
| `ConfigReader\ReadPhpConfigEvent` | a PHP config file is read | `absolutePath()` | no |

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
| `CacheWarmedEvent` | `bin/gacela cache:warm` finished | `moduleCount()`, `failedCount()` | no |

## Cookbook

### Log every resolved class

```php
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;
use Gacela\Framework\Event\GacelaEventInterface;

$config->registerGenericListener(static function (GacelaEventInterface $event): void {
    if ($event instanceof AbstractGacelaClassResolverEvent) {
        error_log($event->toString());
    }
});
```

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

`SetupGacela::setEventDispatcher()` accepts any `EventDispatcherInterface`. Implementations must
provide `dispatch(object $event): void` **and** `hasListeners(string $eventClass): bool` — return
`false` from `hasListeners()` for event classes you don't care about and the framework will skip
allocating them entirely.
