<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\PaymentProcessor;

/**
 * @extends AbstractProvider<PaymentSettings>
 */
final class PaymentDependencyProvider extends AbstractProvider
{
    public const PROCESSOR = 'PAYMENT_PROCESSOR';

    /**
     * Built through the container rather than with `new`, because that is what
     * reads the `#[Inject]` attribute on the processor's constructor.
     */
    #[Provides(self::PROCESSOR)]
    public function processor(Container $container): PaymentProcessor
    {
        return $container->make(PaymentProcessor::class);
    }
}
