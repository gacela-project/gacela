<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing;

use Closure;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\CountryRegistry;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceNumbering;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceRepository;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation\RegisteredCustomerValidator;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;

/**
 * @extends AbstractProvider<BillingConfig>
 */
final class BillingProvider extends AbstractProvider
{
    public const CUSTOMER_FACADE = 'BILLING_CUSTOMER_FACADE';

    public const INVOICE_REPOSITORY = 'BILLING_INVOICE_REPOSITORY';

    public const NUMBER_PREFIX = 'BILLING_NUMBER_PREFIX';

    public const NUMBER_FORMAT = 'BILLING_NUMBER_FORMAT';

    public const COUNTRY_REGISTRY = 'BILLING_COUNTRY_REGISTRY';

    public const VALIDATORS = 'BILLING_VALIDATORS';

    public const VALIDATOR_TAG = 'invoice.validators';

    /**
     * The whole of Billing's dependency on Customer: one Facade, named here so
     * every class below takes it as a constructor argument instead of reaching
     * for the locator.
     */
    #[Provides(self::CUSTOMER_FACADE)]
    public function customerFacade(Container $container): CustomerFacade
    {
        /** @var CustomerFacade $facade */
        $facade = $container->getLocator()->get(CustomerFacade::class);

        return $facade;
    }

    #[Provides(self::INVOICE_REPOSITORY)]
    public function invoiceRepository(): InvoiceRepository
    {
        return new InvoiceRepository();
    }

    /**
     * Asked for by id rather than constructed here, so the lazy registration in
     * `gacela.php` -- which reads configuration -- is the one that decides what
     * is in it.
     */
    #[Provides(self::COUNTRY_REGISTRY)]
    public function countryRegistry(Container $container): CountryRegistry
    {
        /** @var CountryRegistry $registry */
        $registry = $container->get(CountryRegistry::class);

        return $registry;
    }

    /**
     * Registered under its own class name so the `afterResolving()` hook that
     * seeds the sequence can name a type rather than a string.
     */
    #[Provides(InvoiceNumbering::class)]
    public function invoiceNumbering(Container $container): InvoiceNumbering
    {
        /** @var Closure(string, int):string $format */
        $format = $container->get(self::NUMBER_FORMAT);

        return new InvoiceNumbering($format);
    }

    /**
     * What invoice numbers start with. A deployment that has to distinguish its
     * regions wraps this one id with `extendProviderService()`, which reaches
     * only this Provider -- another module using the string "NUMBER_PREFIX"
     * would be left alone.
     */
    #[Provides(self::NUMBER_PREFIX)]
    public function numberPrefix(): string
    {
        return 'INV';
    }

    public function provideModuleDependencies(Container $container): void
    {
        // The app-wide tag is filled in `gacela.php` with the validators that
        // are about invoices and nothing else. This one needs the Customer
        // module, so it is Billing's to add -- and adding it here keeps it out
        // of every other container that consumes the same tag.
        $container->tag(RegisteredCustomerValidator::class, self::VALIDATOR_TAG);

        $container->set(
            self::VALIDATORS,
            static fn (): array => [...$container->tagged(self::VALIDATOR_TAG)],
        );
    }
}
