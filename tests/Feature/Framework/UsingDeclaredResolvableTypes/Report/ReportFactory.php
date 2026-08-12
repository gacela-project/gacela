<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\Report;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DeclaredTypeResolverAwareTrait;

final class ReportFactory extends AbstractFactory
{
    use DeclaredTypeResolverAwareTrait;

    public function createExportedReport(): string
    {
        /** @var ReportExporter $exporter */
        $exporter = $this->getResolvedType('Exporter');

        return $exporter->export();
    }
}
