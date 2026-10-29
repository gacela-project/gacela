<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\Plain;

use GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\AbstractExporter;

final class Exporter extends AbstractExporter
{
    public function export(): string
    {
        return 'plain-exported';
    }
}
