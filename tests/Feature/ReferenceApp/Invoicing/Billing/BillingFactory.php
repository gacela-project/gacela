<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Plugins\PluginStack;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\CountryRegistry;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceIssuer;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceNumbering;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceRepository;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax\TaxCalculatorInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation\InvoiceValidatorInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\NotificationFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock\ClockInterface;

/**
 * Two ways of reaching another module sit side by side here on purpose.
 *
 * The Customer facade comes through the Provider, because Billing depends on
 * customers for its whole reason to exist and that dependency is worth writing
 * down. The Notification facade comes through `#[ServiceMap]`, which is the
 * shorter path for the one call that only announces what already happened --
 * declared as an attribute rather than a `@method` docblock, so a rename moves
 * it and `migrate:service-map` has nothing left to report.
 *
 * @extends AbstractFactory<BillingConfig>
 */
#[ServiceMap(method: 'getNotificationFacade', className: NotificationFacade::class)]
final class BillingFactory extends AbstractFactory
{
    use ServiceResolverAwareTrait;

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function createInvoiceIssuer(): InvoiceIssuer
    {
        /** @var NotificationFacade $notifications */
        $notifications = $this->getNotificationFacade();

        return new InvoiceIssuer(
            $this->getInvoiceRepository(),
            $this->getInvoiceNumbering(),
            $this->createTaxCalculators(),
            $this->getValidators(),
            $this->getCountryRegistry(),
            $this->getCustomerFacade(),
            $notifications,
            $this->clock,
            $this->getNumberPrefix(),
            $this->getConfig()->currency(),
        );
    }

    public function getInvoiceRepository(): InvoiceRepository
    {
        /** @var InvoiceRepository $repository */
        $repository = $this->getProvidedDependency(BillingProvider::INVOICE_REPOSITORY);

        return $repository;
    }

    /**
     * @return PluginStack<TaxCalculatorInterface>
     */
    public function createTaxCalculators(): PluginStack
    {
        return $this->getPluginStack(TaxCalculatorInterface::class);
    }

    /**
     * `AbstractFacade` exposes `getFactory()` and nothing else, so a Facade
     * reaches configuration through here rather than through a `getConfig()` of
     * its own.
     */
    public function currency(): string
    {
        return $this->getConfig()->currency();
    }

    public function getNumberPrefix(): string
    {
        /** @var string $prefix */
        $prefix = $this->getProvidedDependency(BillingProvider::NUMBER_PREFIX);

        return $prefix;
    }

    private function getCountryRegistry(): CountryRegistry
    {
        /** @var CountryRegistry $registry */
        $registry = $this->getProvidedDependency(BillingProvider::COUNTRY_REGISTRY);

        return $registry;
    }

    private function getCustomerFacade(): CustomerFacade
    {
        /** @var CustomerFacade $facade */
        $facade = $this->getProvidedDependency(BillingProvider::CUSTOMER_FACADE);

        return $facade;
    }

    private function getInvoiceNumbering(): InvoiceNumbering
    {
        return $this->getProvidedDependency(InvoiceNumbering::class);
    }

    /**
     * @return list<InvoiceValidatorInterface>
     */
    private function getValidators(): array
    {
        /** @var list<InvoiceValidatorInterface> $validators */
        $validators = $this->getProvidedDependency(BillingProvider::VALIDATORS);

        return $validators;
    }
}
