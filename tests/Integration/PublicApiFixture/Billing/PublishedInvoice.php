<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PublicApiFixture\Billing;

use Gacela\Framework\Attribute\PublicApi;

/**
 * Published by the attribute, where it already lives -- the escape hatch for the
 * class a project does not want to move.
 */
#[PublicApi]
final class PublishedInvoice
{
    public function number(): string
    {
        return 'INV-1';
    }
}
