<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendProviderService\Checkout;

use Gacela\Framework\AbstractFactory;

final class CheckoutFactory extends AbstractFactory
{
    public function label(): string
    {
        /** @var list<string> $labels */
        $labels = $this->getProvidedDependency(CheckoutProvider::LABEL);

        return implode('-', $labels);
    }
}
