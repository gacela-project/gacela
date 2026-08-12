<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\Report;

use GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\AbstractExporter;

final class ReportExporter extends AbstractExporter
{
    public function export(): string
    {
        return 'report-exported';
    }
}
