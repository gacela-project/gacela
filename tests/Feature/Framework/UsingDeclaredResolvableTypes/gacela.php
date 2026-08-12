<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes;

use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    // A fifth kind, declared by the project: two suffixes, so both finder
    // rules are exercised -- `Report\ReportExporter` with the module prefix
    // and `Invoice\Feed` without it.
    $config->addResolvableType('Exporter', AbstractExporter::class, ['Exporter', 'Feed']);
};
