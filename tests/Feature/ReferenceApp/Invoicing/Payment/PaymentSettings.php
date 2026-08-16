<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment;

use Gacela\Framework\AbstractConfig;

/**
 * Named `PaymentSettings`, not `PaymentConfig`.
 *
 * This module arrived from the codebase that preceded the rest of the
 * application and kept its own vocabulary. `gacela.php` teaches the resolver
 * those four names with `addSuffixTypeConfig()` and its three siblings, which
 * is what lets the module stay as it is instead of being renamed on the way in
 * -- and a rename is the one change that touches every call site at once.
 */
final class PaymentSettings extends AbstractConfig
{
    public function gatewayEndpoint(): string
    {
        return $this->getString('payment.gateway_endpoint');
    }

    public function defaultMethod(): string
    {
        return $this->getString('payment.default_method');
    }
}
