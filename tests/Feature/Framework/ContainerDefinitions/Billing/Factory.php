<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerDefinitions\Billing;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createInvoice(): Invoice
    {
        return $this->make(Invoice::class);
    }
}
