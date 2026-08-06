<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\Container\Resolving\InvoiceService;
use GacelaTest\Integration\Framework\Container\Resolving\LoggerAwareInterface;
use GacelaTest\Integration\Framework\Container\Resolving\PlainService;
use GacelaTest\Integration\Framework\Container\Resolving\ReportService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class AfterResolvingHookTest extends TestCase
{
    protected function tearDown(): void
    {
        (new ReflectionClass(Gacela::class))->getMethod('resetCache')->invoke(null);
    }

    public function test_a_hook_registered_for_a_concrete_class_runs_on_resolution(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(
                ReportService::class,
                static fn (ReportService $service) => $service->setLogger('file'),
            );
        });

        $service = Gacela::container()->get(ReportService::class);

        self::assertInstanceOf(ReportService::class, $service);
        self::assertSame('file', $service->logger());
    }

    public function test_a_hook_registered_for_an_interface_runs_for_every_implementation(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(
                LoggerAwareInterface::class,
                static fn (LoggerAwareInterface $service) => $service->setLogger('file'),
            );
        });

        $container = Gacela::container();

        // This is the case the issue opens with, and the reason the hook checks
        // each instance rather than doing a map lookup on the requested id.
        self::assertSame('file', $container->get(ReportService::class)?->logger());
        self::assertSame('file', $container->get(InvoiceService::class)?->logger());
    }

    public function test_a_service_that_does_not_match_is_left_alone(): void
    {
        $seen = [];

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$seen): void {
            $config->afterResolving(
                LoggerAwareInterface::class,
                static function (object $service) use (&$seen): void {
                    $seen[] = $service::class;
                },
            );
        });

        Gacela::container()->get(PlainService::class);

        self::assertSame([], $seen);
    }

    public function test_the_hook_receives_the_container_as_its_second_argument(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(
                ReportService::class,
                static fn (ReportService $service, Container $container) => $service->setLogger($container::class),
            );
        });

        self::assertSame(Container::class, Gacela::container()->get(ReportService::class)?->logger());
    }

    public function test_several_hooks_for_one_id_run_in_registration_order(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(ReportService::class, static fn (ReportService $s) => $s->setLogger('first'));
            $config->afterResolving(ReportService::class, static fn (ReportService $s) => $s->setLogger(
                $s->logger() . '+second',
            ));
        });

        self::assertSame('first+second', Gacela::container()->get(ReportService::class)?->logger());
    }

    public function test_a_hook_runs_for_a_service_built_through_make(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(
                LoggerAwareInterface::class,
                static fn (LoggerAwareInterface $service) => $service->setLogger('file'),
            );
        });

        // make() is the keystone construction path for domain objects, so a
        // hook that skipped it would miss most of what a module builds.
        self::assertSame('file', Gacela::container()->make(ReportService::class)->logger());
    }

    public function test_a_hook_runs_for_a_service_obtained_with_get_or_fail(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(
                ReportService::class,
                static fn (ReportService $service) => $service->setLogger('file'),
            );
        });

        /** @var ReportService $service */
        $service = Gacela::container()->getOrFail(ReportService::class);

        self::assertSame('file', $service->logger());
    }

    public function test_a_throwing_hook_does_not_leave_a_half_built_service_in_the_cache(): void
    {
        $seen = [];

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$seen): void {
            // A handler registry is the config-level path that produces a
            // *stored* instance -- bindings and factories build a new one per
            // resolution, so only this one has a cache to be left dirty.
            $config->addHandlerRegistry('dispatcher', ['a' => ReportService::class]);
            $config->afterResolving('dispatcher', static function (object $service) use (&$seen): never {
                $seen[] = $service;
                throw new RuntimeException('hook failed');
            });
        });

        $container = Gacela::container();

        try {
            $container->get('dispatcher');
            self::fail('the hook was expected to throw');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('hook failed', $runtimeException->getMessage());
        }

        self::assertCount(1, $seen);

        // The instance whose post-construction wiring failed is dropped, so the
        // next caller is not handed it as though the hook had succeeded.
        self::assertNotSame($seen[0], $container->get('dispatcher'));
    }

    public function test_a_hook_registered_for_something_else_does_not_stop_a_later_matching_one(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            // Registered first and matching nothing here: iteration has to skip
            // it and keep going, not give up on the rest.
            $config->afterResolving(PlainService::class, static fn (PlainService $s): \GacelaTest\Integration\Framework\Container\Resolving\PlainService => $s);
            $config->afterResolving(
                ReportService::class,
                static fn (ReportService $service) => $service->setLogger('file'),
            );
        });

        self::assertSame('file', Gacela::container()->get(ReportService::class)?->logger());
    }

    public function test_a_hook_does_not_fire_for_a_service_that_is_not_an_object(): void
    {
        $fired = false;

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$fired): void {
            $config->addFactory('scalar-service', static fn (): string => 'value');
            $config->afterResolving('scalar-service', static function () use (&$fired): void {
                $fired = true;
            });
        });

        // The id matches exactly, so only the "is it an object" guard keeps the
        // hook from being handed a string it cannot wire.
        self::assertSame('value', Gacela::container()->get('scalar-service'));
        self::assertFalse($fired);
    }

    public function test_a_container_with_no_hooks_resolves_normally(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->addBinding(LoggerAwareInterface::class, ReportService::class);
        });

        $service = Gacela::container()->get(LoggerAwareInterface::class);

        self::assertInstanceOf(ReportService::class, $service);
        self::assertNull($service->logger());
    }

    public function test_reset_cache_clears_registered_hooks(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(
                ReportService::class,
                static fn (ReportService $service) => $service->setLogger('file'),
            );
        });

        self::assertSame('file', Gacela::container()->get(ReportService::class)?->logger());

        // Re-bootstrapping without the hook must not keep firing the old one.
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        self::assertNull(Gacela::container()->get(ReportService::class)?->logger());
    }

    /**
     * @param callable(GacelaConfig):void $configFn
     */
    private function bootstrapWith(callable $configFn): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $configFn($config);
        });
    }
}
