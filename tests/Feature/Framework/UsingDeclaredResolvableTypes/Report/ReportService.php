<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\Report;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

#[ServiceMap(method: 'getSalesExporter', className: SalesExporter::class)]
#[ServiceMap(method: 'getAuditExporter', className: AuditExporter::class)]
final class ReportService
{
    use ServiceResolverAwareTrait;
}
