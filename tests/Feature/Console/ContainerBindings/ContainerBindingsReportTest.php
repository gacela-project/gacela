<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ContainerBindings;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\PaymentGatewayInterface;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\StripeGateway;
use PHPUnit\Framework\TestCase;

/**
 * `debug:module` reports the container's bindings, so the report has to list
 * *every* one. A report that shows the first entry of each map looks correct
 * next to a small fixture and quietly hides the rest of a real application.
 */
final class ContainerBindingsReportTest extends TestCase
{
    private ConsoleFacade $facade;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(PaymentGatewayInterface::class, StripeGateway::class);
            $config->addBinding(FirstContract::class, FirstImplementation::class);
            $config->addBinding(SecondContract::class, SecondImplementation::class);

            $config->when(FirstConsumer::class)
                ->needs(FirstContract::class)
                ->give(FirstImplementation::class);
            $config->when(SecondConsumer::class)
                ->needs(SecondContract::class)
                ->give(SecondImplementation::class);
        });

        $this->facade = new ConsoleFacade();
    }

    public function test_reports_every_binding_not_only_the_first(): void
    {
        $bindings = $this->facade->getContainerBindings();

        self::assertSame(StripeGateway::class, $bindings[PaymentGatewayInterface::class] ?? null);
        self::assertSame(FirstImplementation::class, $bindings[FirstContract::class] ?? null);
        self::assertSame(SecondImplementation::class, $bindings[SecondContract::class] ?? null);
    }

    public function test_reports_every_contextual_binding_not_only_the_first(): void
    {
        $contextual = $this->facade->getContainerContextualBindings();

        self::assertSame(
            FirstImplementation::class,
            $contextual[FirstConsumer::class][FirstContract::class] ?? null,
        );
        self::assertSame(
            SecondImplementation::class,
            $contextual[SecondConsumer::class][SecondContract::class] ?? null,
        );
    }
}
