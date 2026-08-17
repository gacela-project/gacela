<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DeclaredTypeResolverAwareTrait;
use Gacela\Framework\Plugins\HandlerRegistry;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\AttemptId;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\Method\PaymentMethodHandlerInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\PaymentProcessor;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\PaymentReceipt;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Resilience\RetryPolicyInterface;

/**
 * Named `PaymentBuilder` for the same reason `PaymentSettings` is: this module
 * predates the rest of the application. `addSuffixTypeFactory('Builder')` in
 * `gacela.php` is what makes it a Factory.
 *
 * @extends AbstractFactory<PaymentSettings>
 */
final class PaymentBuilder extends AbstractFactory
{
    use DeclaredTypeResolverAwareTrait;

    /**
     * A capture worth retrying, unlike anything else in the application. The
     * contextual binding in `gacela.php` is what makes this constructor differ
     * from the notification module's, which asks for the same interface.
     */
    public function __construct(
        private readonly RetryPolicyInterface $retryPolicy,
    ) {
    }

    /**
     * @param string $method '' to settle the way this deployment's configuration
     *                       says to -- `payment.default_method`, which only
     *                       `config/app-prod.php` sets, so outside production the
     *                       schema's declared default answers instead
     */
    public function capture(string $invoiceNumber, int $amountCents, string $method = ''): PaymentReceipt
    {
        return $this->getPaymentProcessor()->capture(
            $this->getGateway(),
            $this->getMethodHandler($method === '' ? $this->getConfig()->defaultMethod() : $method),
            $this->createAttemptId(),
            $invoiceNumber,
            $amountCents,
        );
    }

    public function getPaymentProcessor(): PaymentProcessor
    {
        /** @var PaymentProcessor $processor */
        $processor = $this->getProvidedDependency(PaymentDependencyProvider::PROCESSOR);

        return $processor;
    }

    public function createAttemptId(): AttemptId
    {
        /** @var AttemptId $attempt */
        $attempt = $this->getProvidedDependency(AttemptId::class);

        return $attempt;
    }

    public function retryAttempts(): int
    {
        return $this->retryPolicy->attempts();
    }

    public function gatewayEndpoint(): string
    {
        return $this->getConfig()->gatewayEndpoint();
    }

    /**
     * @return list<string>
     */
    public function supportedMethods(): array
    {
        return $this->getMethodHandlers()->keys();
    }

    private function getGateway(): AbstractGateway
    {
        /** @var AbstractGateway $gateway */
        $gateway = $this->getResolvedType('Gateway');

        return $gateway;
    }

    private function getMethodHandler(string $method): PaymentMethodHandlerInterface
    {
        /** @var PaymentMethodHandlerInterface $handler */
        $handler = $this->getMethodHandlers()->get($method);

        return $handler;
    }

    /**
     * @return HandlerRegistry<PaymentMethodHandlerInterface, string>
     */
    private function getMethodHandlers(): HandlerRegistry
    {
        /** @var HandlerRegistry<PaymentMethodHandlerInterface, string> $registry */
        $registry = $this->getProvidedDependency(PaymentMethodHandlerInterface::class);

        return $registry;
    }
}
