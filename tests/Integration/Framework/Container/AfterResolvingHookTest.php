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

    /**
     * Pins the semantics rather than asserting they are ideal. The hook fires
     * per *resolution*, not per construction: a shared instance fetched three
     * times runs the callback three times, on the one object the constructor
     * built once.
     *
     * The container documents this upstream ("this runs on every resolution");
     * what was wrong was Gacela's own construction-flavoured wording around it.
     * A callback that is not idempotent — appending to a collection, bumping a
     * counter, registering a listener — has to account for it.
     */
    public function test_a_hook_fires_once_per_resolution_not_once_per_instance(): void
    {
        $calls = 0;

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$calls): void {
            // Bound as an already-built instance, so the container hands the
            // same object back every time and construction cannot be what
            // drives the callback count.
            $config->addBinding(ReportService::class, new ReportService());
            $config->afterResolving(
                ReportService::class,
                static function () use (&$calls): void {
                    ++$calls;
                },
            );
        });

        $container = Gacela::container();
        $first = $container->get(ReportService::class);
        $container->get(ReportService::class);
        $third = $container->get(ReportService::class);

        self::assertSame($first, $third, 'the container hands back one shared instance');
        self::assertSame(3, $calls, 'but the hook ran once per get()');
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
        self::assertSame('file', $this->resolvedReportService()->logger());
        self::assertSame('file', $this->resolvedInvoiceService()->logger());
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

        self::assertSame(Container::class, $this->resolvedReportService()->logger());
    }

    public function test_several_hooks_for_one_id_run_in_registration_order(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(ReportService::class, static fn (ReportService $s) => $s->setLogger('first'));
            $config->afterResolving(ReportService::class, static fn (ReportService $s) => $s->setLogger(
                $s->logger() . '+second',
            ));
        });

        self::assertSame('first+second', $this->resolvedReportService()->logger());
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

    public function test_configured_hooks_run_for_every_scope_resolution_entry_point(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(
                LoggerAwareInterface::class,
                static fn (LoggerAwareInterface $service) => $service->setLogger('scoped'),
            );
        });

        $scope = Gacela::container()->createScope();
        $scope->set('report', new ReportService());
        $scope->set('invoice', new InvoiceService());

        $report = $scope->get('report');
        $invoice = $scope->getOrFail('invoice');

        self::assertInstanceOf(ReportService::class, $report);
        self::assertInstanceOf(InvoiceService::class, $invoice);
        self::assertSame('scoped', $report->logger());
        self::assertSame('scoped', $invoice->logger());
        self::assertSame('scoped', $scope->make(ReportService::class)->logger());
    }

    public function test_a_scope_runs_inherited_hooks_in_registration_order(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->afterResolving(ReportService::class, static fn (ReportService $s) => $s->setLogger('first'));
            $config->afterResolving(
                ReportService::class,
                static fn (ReportService $s) => $s->setLogger($s->logger() . '+second'),
            );
        });

        $service = Gacela::container()->createScope()->make(ReportService::class);

        self::assertSame('first+second', $service->logger());
    }

    public function test_a_parent_owned_service_runs_a_hook_only_once_through_a_scope(): void
    {
        $calls = 0;
        $service = new ReportService();

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$calls, $service): void {
            $config->addBinding(ReportService::class, $service);
            $config->afterResolving(ReportService::class, static function () use (&$calls): void {
                ++$calls;
            });
        });

        self::assertSame($service, Gacela::container()->createScope()->get(ReportService::class));
        self::assertSame(1, $calls);
    }

    public function test_a_throwing_inherited_hook_removes_the_scoped_service(): void
    {
        $seen = [];

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$seen): void {
            $config->afterResolving('report', static function (ReportService $service) use (&$seen): never {
                $seen[] = $service;
                throw new RuntimeException('scope hook failed');
            });
        });

        $scope = Gacela::container()->createScope();
        $scope->set('report', static fn (): ReportService => new ReportService());

        try {
            $scope->get('report');
            self::fail('the inherited hook was expected to throw');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('scope hook failed', $runtimeException->getMessage());
        }

        self::assertCount(1, $seen);
        self::assertNotSame($seen[0], $scope->get('report'));
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

        self::assertSame('file', $this->resolvedReportService()->logger());
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

        self::assertSame('file', $this->resolvedReportService()->logger());

        // Re-bootstrapping without the hook must not keep firing the old one.
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        self::assertNull($this->resolvedReportService()->logger());
    }

    /**
     * @param callable(GacelaConfig):void $configFn
     */
    /**
     * Resolving through `?->` made a service that failed to resolve read as a
     * null logger, so the assertion failed on the value instead of on the cause.
     */
    private function resolvedReportService(): ReportService
    {
        $service = Gacela::container()->get(ReportService::class);
        self::assertInstanceOf(ReportService::class, $service);

        return $service;
    }

    private function resolvedInvoiceService(): InvoiceService
    {
        $service = Gacela::container()->get(InvoiceService::class);
        self::assertInstanceOf(InvoiceService::class, $service);

        return $service;
    }

    private function bootstrapWith(callable $configFn): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $configFn($config);
        });
    }
}
