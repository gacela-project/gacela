<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\Method;

/**
 * One handler per payment method, dispatched by key.
 *
 * Declared with `GacelaConfig::addHandlerRegistry()`: a registry answers "the
 * handler for this key", where a plugin stack answers "every implementation of
 * this". Paying by card is the first question, not the second.
 */
interface PaymentMethodHandlerInterface
{
    public function method(): string;
}
