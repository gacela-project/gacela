<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\Event\Cache\CacheClearedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNameCacheCachedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNameInMemoryCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNamePhpCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesCacheCachedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesInMemoryCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesPhpCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameCachedFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameInvalidCandidateFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameValidCandidateFoundEvent;
use Gacela\Framework\Event\Config\ConfigInitializedEvent;
use Gacela\Framework\Event\Config\ConfigKeyNotFoundEvent;
use Gacela\Framework\Event\Config\ConfigKeyReadEvent;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Event\Provider\ProviderRegisteredEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `toString()` is the whole of an event's public surface once it reaches a
 * listener -- it is what a consumer logs. Nothing else asserts the shape, so a
 * dropped field or a renamed key would go out silently.
 */
final class EventToStringTest extends TestCase
{
    /**
     * @return iterable<string, array{GacelaEventInterface, string}>
     */
    public static function eventProvider(): iterable
    {
        yield 'cache cleared' => [
            new CacheClearedEvent('/tmp/gacela.php'),
            CacheClearedEvent::class . ' {cacheFile:"/tmp/gacela.php"}',
        ];

        yield 'config initialized' => [
            new ConfigInitializedEvent(3),
            ConfigInitializedEvent::class . ' {keyCount:3}',
        ];

        yield 'config key read' => [
            new ConfigKeyReadEvent('some.key'),
            ConfigKeyReadEvent::class . ' {key:"some.key"}',
        ];

        yield 'config key not found' => [
            new ConfigKeyNotFoundEvent('missing.key'),
            ConfigKeyNotFoundEvent::class . ' {key:"missing.key"}',
        ];

        yield 'binding registered' => [
            new BindingRegisteredEvent('some-id'),
            BindingRegisteredEvent::class . ' {id:"some-id"}',
        ];

        yield 'service resolved' => [
            new ServiceResolvedEvent('some-id'),
            ServiceResolvedEvent::class . ' {id:"some-id"}',
        ];

        yield 'provider registered' => [
            new ProviderRegisteredEvent('App\Checkout\CheckoutProvider', 'Checkout'),
            ProviderRegisteredEvent::class . ' {providerClass:"App\Checkout\CheckoutProvider", moduleName:"Checkout"}',
        ];

        yield 'class name cached found' => [
            new ClassNameCachedFoundEvent('cache-key', 'App\Checkout\CheckoutFacade'),
            ClassNameCachedFoundEvent::class . ' {cacheKey:"cache-key", className:"App\Checkout\CheckoutFacade"}',
        ];

        yield 'php cache created' => [
            new ClassNamePhpCacheCreatedEvent('/tmp/cache.php'),
            ClassNamePhpCacheCreatedEvent::class . ' {cacheDir:"/tmp/cache.php"}',
        ];

        yield 'class name cache cached' => [
            new ClassNameCacheCachedEvent(),
            ClassNameCacheCachedEvent::class . ' {}',
        ];

        yield 'class name in-memory cache created' => [
            new ClassNameInMemoryCacheCreatedEvent(),
            ClassNameInMemoryCacheCreatedEvent::class . ' {}',
        ];

        yield 'custom services cache cached' => [
            new CustomServicesCacheCachedEvent(),
            CustomServicesCacheCachedEvent::class . ' {}',
        ];

        yield 'custom services in-memory cache created' => [
            new CustomServicesInMemoryCacheCreatedEvent(),
            CustomServicesInMemoryCacheCreatedEvent::class . ' {}',
        ];

        yield 'custom services php cache created' => [
            new CustomServicesPhpCacheCreatedEvent(),
            CustomServicesPhpCacheCreatedEvent::class . ' {}',
        ];

        yield 'valid candidate found' => [
            new ClassNameValidCandidateFoundEvent('App\Checkout\CheckoutFacade'),
            ClassNameValidCandidateFoundEvent::class . ' {className:"App\Checkout\CheckoutFacade"}',
        ];

        yield 'invalid candidate found' => [
            new ClassNameInvalidCandidateFoundEvent('App\Checkout\Nope'),
            ClassNameInvalidCandidateFoundEvent::class . ' {className:"App\Checkout\Nope"}',
        ];

        yield 'class name not found' => [
            new ClassNameNotFoundEvent(ClassInfo::from('App\Checkout\CheckoutFacade', 'Facade'), ['Facade', 'Factory']),
            ClassNameNotFoundEvent::class . ' {classInfo:"'
                . ClassInfo::from('App\Checkout\CheckoutFacade', 'Facade')->toString()
                . '", resolvableTypes:"Facade,Factory"}',
        ];
    }

    #[DataProvider('eventProvider')]
    public function test_it_renders_every_field_it_carries(GacelaEventInterface $event, string $expected): void
    {
        self::assertSame($expected, $event->toString());
    }

    /**
     * The name is in the string so a listener logging several events can tell
     * them apart; two events rendering identically would be indistinguishable.
     */
    #[DataProvider('eventProvider')]
    public function test_it_names_itself(GacelaEventInterface $event, string $expected): void
    {
        self::assertStringStartsWith($event::class . ' {', $event->toString());
    }
}
