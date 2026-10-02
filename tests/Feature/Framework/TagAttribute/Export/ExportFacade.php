<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TagAttribute\Export;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<ExportFactory>
 */
final class ExportFacade extends AbstractFacade
{
    /**
     * @return list<string>
     */
    public function exporterNames(): array
    {
        return array_map(static fn (Exporter $exporter): string => $exporter->name(), $this->getFactory()->createExporters());
    }
}
