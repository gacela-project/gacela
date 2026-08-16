<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\Method;

final class CardPaymentHandler implements PaymentMethodHandlerInterface
{
    public function method(): string
    {
        return 'card';
    }
}
