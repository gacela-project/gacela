<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\Invoice;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DeclaredTypeResolverAwareTrait;

final class InvoiceFactory extends AbstractFactory
{
    use DeclaredTypeResolverAwareTrait;

    public function createFedInvoice(): string
    {
        /** @var Feed $exporter */
        $exporter = $this->getResolvedType('Exporter');

        return $exporter->export();
    }
}
