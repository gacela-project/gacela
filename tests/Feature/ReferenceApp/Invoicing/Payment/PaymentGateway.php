<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment;

use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\PaymentReceipt;

/**
 * The `Gateway` this module resolves to.
 *
 * Reached from the Factory with `getResolvedType('Gateway')` and never
 * constructed by name, so a deployment that ships a different gateway class in
 * this module replaces it the same way it would replace a Factory.
 */
final class PaymentGateway extends AbstractGateway
{
    public function capture(string $invoiceNumber, int $amountCents): PaymentReceipt
    {
        /** @var PaymentSettings $settings */
        $settings = $this->getConfig();

        return new PaymentReceipt($invoiceNumber, $amountCents, $settings->gatewayEndpoint());
    }
}
