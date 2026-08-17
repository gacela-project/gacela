<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain;

use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Plugins\PluginStack;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax\TaxCalculatorInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation\InvoiceValidatorInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Event\InvoiceIssuedEvent;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock\ClockInterface;
use InvalidArgumentException;

use function sprintf;

/**
 * The one place an invoice comes into existence.
 *
 * One other module is reached from here, through its Facade: the customer
 * directory, to learn where the customer is. Billing depends on customers for
 * its whole reason to exist, and that dependency is written down.
 *
 * Saying that the invoice exists is a different kind of thing, and it is done a
 * different way: an event, dispatched through the dispatcher this class was
 * given. Notification sends the mail and Reporting could update a projection
 * without either of them appearing here -- which is what lets the announcement
 * gain a second listener without this file changing, and what keeps `debug:graph`
 * from ever drawing an edge from Billing to whoever is listening.
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
        private readonly EventDispatcherInterface $events,
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
        $this->announce($invoice, $profile->getName());

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

    /**
     * Guarded like every dispatch site in the framework, and for the same
     * reason: with nothing listening, the event is never built. An application
     * that removes the listener from `gacela.php` pays nothing for the line
     * below staying here.
     */
    private function announce(InvoiceRecord $invoice, string $customerName): void
    {
        if (!$this->events->hasListeners(InvoiceIssuedEvent::class)) {
            return;
        }

        $this->events->dispatch(new InvoiceIssuedEvent(
            $invoice->getNumber(),
            $customerName,
            $invoice->getGrossCents(),
            $this->currency,
        ));
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
