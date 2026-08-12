<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\Invoice;

use GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes\AbstractExporter;

/**
 * The kind's second suffix, and no module prefix: found by the other finder
 * rule, the same one that finds a bare `Facade`.
 */
final class Feed extends AbstractExporter
{
    public function export(): string
    {
        return 'invoice-fed';
    }
}
