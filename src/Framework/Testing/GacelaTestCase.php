<?php

declare(strict_types=1);

namespace Gacela\Framework\Testing;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;
use function array_values;
use function sprintf;

/**
 * Base class for tests that bootstrap a Gacela application.
 *
 * It removes the reset boilerplate every Gacela test needs: each
 * bootstrap starts from a clean in-memory state, and tearDown() drops all
 * Gacela singletons so state never leaks into the next test.
 *
 * Every bootstrap also records the framework lifecycle events, which
 * powers the container assertions:
 *
 * ```php
 * final class CheckoutTest extends GacelaTestCase
 * {
 *     public function test_facade_resolves_payment_gateway(): void
 *     {
 *         $this->bootstrapGacelaWithConfig(__DIR__, ['retries' => 3]);
 *
 *         (new CheckoutFacade())->pay();
 *
 *         $this->assertServiceResolved(PaymentGateway::class);
 *     }
 * }
 * ```
 */
abstract class GacelaTestCase extends TestCase
{
    use ContainerFixture;

    /** @var list<GacelaEventInterface> */
    private array $recordedGacelaEvents = [];

    protected function tearDown(): void
    {
        $this->resetContainer();
        $this->recordedGacelaEvents = [];
    }

    /**
     * Bootstrap Gacela from a clean in-memory state, recording all
     * framework lifecycle events dispatched from this bootstrap onwards.
     *
     * @param null|Closure(GacelaConfig):void $configFn
     */
    protected function bootstrapGacela(string $appRootDir, ?Closure $configFn = null): void
    {
        $this->recordedGacelaEvents = [];

        Gacela::bootstrap($appRootDir, function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $config->registerGenericListener(function (GacelaEventInterface $event): void {
                $this->recordedGacelaEvents[] = $event;
            });

            if ($configFn instanceof Closure) {
                $configFn($config);
            }
        });
    }

    /**
     * Bootstrap Gacela with the given config key-values, the most common
     * override needed in tests.
     *
     * @param array<string,mixed> $configKeyValues
     */
    protected function bootstrapGacelaWithConfig(string $appRootDir, array $configKeyValues): void
    {
        $this->bootstrapGacela($appRootDir, static function (GacelaConfig $config) use ($configKeyValues): void {
            $config->addAppConfigKeyValues($configKeyValues);
        });
    }

    /**
     * All framework lifecycle events recorded since the last bootstrap.
     *
     * @return list<GacelaEventInterface>
     */
    protected function recordedGacelaEvents(): array
    {
        return $this->recordedGacelaEvents;
    }

    /**
     * The recorded events of one type, in dispatch order.
     *
     * @template T of GacelaEventInterface
     *
     * @param class-string<T> $eventClass
     *
     * @return list<T>
     */
    protected function recordedGacelaEventsOf(string $eventClass): array
    {
        /** @var list<T> */
        return array_values(array_filter(
            $this->recordedGacelaEvents,
            static fn (GacelaEventInterface $event): bool => $event instanceof $eventClass,
        ));
    }

    /**
     * Assert that the container instantiated the given service id since
     * the last bootstrap.
     */
    protected function assertServiceResolved(string $serviceId): void
    {
        self::assertContains(
            $serviceId,
            array_map(
                static fn (ServiceResolvedEvent $event): string => $event->id(),
                $this->recordedGacelaEventsOf(ServiceResolvedEvent::class),
            ),
            sprintf('Service "%s" was not resolved by the container.', $serviceId),
        );
    }

    /**
     * Assert that a binding/alias/contextual binding was registered under
     * the given id since the last bootstrap.
     */
    protected function assertBindingRegistered(string $id): void
    {
        self::assertContains(
            $id,
            array_map(
                static fn (BindingRegisteredEvent $event): string => $event->id(),
                $this->recordedGacelaEventsOf(BindingRegisteredEvent::class),
            ),
            sprintf('Binding "%s" was not registered in the container.', $id),
        );
    }
}
