<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain;

use Gacela\Framework\Plugins\PluginStack;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax\TaxCalculatorInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation\InvoiceValidatorInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\NotificationFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock\ClockInterface;
use InvalidArgumentException;

use function sprintf;

/**
 * The one place an invoice comes into existence.
 *
 * Two other modules are reached from here, and both through their Facade: the
 * customer directory to learn where the customer is, and notifications to say
 * the invoice exists. Nothing else of theirs is named.
 */
final class InvoiceIssuer
{
    /**
     * @param PluginStack<TaxCalculatorInterface> $taxCalculators
     * @param list<InvoiceValidatorInterface> $validators
     */
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceNumbering $numbering,
        private readonly PluginStack $taxCalculators,
        private readonly array $validators,
        private readonly CountryRegistry $countries,
        private readonly CustomerFacade $customers,
        private readonly NotificationFacade $notifications,
        private readonly ClockInterface $clock,
        private readonly string $numberPrefix,
        private readonly string $currency,
    ) {
    }

    public function issue(string $customerReference, int $netCents): InvoiceRecord
    {
        $this->refuseUnlessValid($customerReference, $netCents);

        $profile = $this->customers->findCustomer($customerReference);
        $this->refuseUnsupportedCountry($profile->getCountryCode());

        $taxCents = $this->taxFor($netCents, $profile->getCountryCode());
        $number = $this->numbering->next($this->numberPrefix);

        $invoice = InvoiceRecord::fromArray([
            'number' => $number,
            'customerReference' => $customerReference,
            'netCents' => $netCents,
            'taxCents' => $taxCents,
            'grossCents' => $netCents + $taxCents,
            'currency' => $this->currency,
            'issuedOn' => $this->clock->today(),
        ]);

        $this->invoices->save($invoice);

        $this->notifications->notifyInvoiceIssued(
            $profile->getName(),
            $number,
            sprintf('%d %s due', $invoice->getGrossCents(), $this->currency),
        );

        return $invoice;
    }

    /**
     * @return list<string>
     */
    public function validatorNames(): array
    {
        $names = [];

        foreach ($this->validators as $validator) {
            $names[] = $validator->name();
        }

        return $names;
    }

    private function refuseUnlessValid(string $customerReference, int $netCents): void
    {
        foreach ($this->validators as $validator) {
            $reason = $validator->reasonToRefuse($customerReference, $netCents);

            if ($reason !== null) {
                throw new InvalidArgumentException($reason);
            }
        }
    }

    private function refuseUnsupportedCountry(string $countryCode): void
    {
        if ($this->countries->accepts($countryCode)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'this business does not invoice into "%s"; supported: %s',
            $countryCode,
            implode(', ', $this->countries->supportedCountryCodes()),
        ));
    }

    private function taxFor(int $netCents, string $countryCode): int
    {
        $taxCents = 0;

        foreach ($this->taxCalculators as $calculator) {
            $taxCents += $calculator->taxFor($netCents, $taxCents, $countryCode);
        }

        return $taxCents;
    }
}
