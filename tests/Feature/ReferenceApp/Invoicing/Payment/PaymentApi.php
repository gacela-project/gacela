<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\PaymentReceipt;

/**
 * The module's Facade, under the name it has always had here.
 * `addSuffixTypeFacade('Api')` in `gacela.php` is what makes it one.
 *
 * @extends AbstractFacade<PaymentBuilder>
 */
final class PaymentApi extends AbstractFacade
{
    public function pay(string $invoiceNumber, int $amountCents, string $method): PaymentReceipt
    {
        return $this->getFactory()->capture($invoiceNumber, $amountCents, $method);
    }

    /**
     * @return list<string>
     */
    public function ledgerEntries(): array
    {
        return $this->getFactory()->getPaymentProcessor()->ledgerEntries();
    }

    /**
     * @return list<string>
     */
    public function supportedMethods(): array
    {
        return $this->getFactory()->supportedMethods();
    }

    public function retryAttempts(): int
    {
        return $this->getFactory()->retryAttempts();
    }

    public function gatewayEndpoint(): string
    {
        return $this->getFactory()->gatewayEndpoint();
    }
}
