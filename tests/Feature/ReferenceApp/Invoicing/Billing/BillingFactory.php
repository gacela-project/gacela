<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Plugins\PluginStack;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\CountryRegistry;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceIssuer;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceNumbering;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceRepository;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax\TaxCalculatorInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation\InvoiceValidatorInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock\ClockInterface;

/**
 * Two ways of depending on something else sit side by side here on purpose.
 *
 * The Customer facade comes through the Provider, because Billing depends on
 * customers for its whole reason to exist and that dependency is worth writing
 * down. The event dispatcher comes through the Provider as well -- it is a
 * dependency like any other, which is the whole point of the arrangement: no
 * trait, no static call, and a test can hand this Factory a dispatcher of its
 * own. What Billing announces through it reaches whoever `gacela.php` says, and
 * this module never learns who that is.
 *
 * @extends AbstractFactory<BillingConfig>
 */
final class BillingFactory extends AbstractFactory
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function createInvoiceIssuer(): InvoiceIssuer
    {
        return new InvoiceIssuer(
            $this->getInvoiceRepository(),
            $this->getInvoiceNumbering(),
            $this->createTaxCalculators(),
            $this->getValidators(),
            $this->getCountryRegistry(),
            $this->getCustomerFacade(),
            $this->getEventDispatcher(),
            $this->clock,
            $this->getNumberPrefix(),
            $this->getConfig()->currency(),
        );
    }

    /**
     * The dispatcher the application is running with, asked for by its
     * interface. Whatever `setEventDispatcher()` installed is what arrives
     * here -- including a host framework's own PSR-14 bus.
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->getProvidedDependency(EventDispatcherInterface::class);
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
