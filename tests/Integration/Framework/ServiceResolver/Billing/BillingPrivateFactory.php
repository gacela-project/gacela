<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Billing;

use Gacela\Framework\AbstractFactory;

final class BillingPrivateFactory extends AbstractFactory
{
    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }
}
