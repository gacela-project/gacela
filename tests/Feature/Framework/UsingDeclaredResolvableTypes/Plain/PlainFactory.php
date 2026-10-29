<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\Plain;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DeclaredTypeResolverAwareTrait;

final class PlainFactory extends AbstractFactory
{
    use DeclaredTypeResolverAwareTrait;

    public function createExportedReport(): string
    {
        /** @var Exporter $exporter */
        $exporter = $this->getResolvedType('Exporter');

        return $exporter->export();
    }
}
