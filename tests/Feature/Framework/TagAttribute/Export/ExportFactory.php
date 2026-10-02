<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TagAttribute\Export;

use Gacela\Framework\AbstractFactory;

final class ExportFactory extends AbstractFactory
{
    /**
     * @return list<Exporter>
     */
    public function createExporters(): array
    {
        /** @var list<Exporter> $exporters */
        $exporters = $this->getProvidedDependency(ExportProvider::EXPORTERS);

        return $exporters;
    }
}
