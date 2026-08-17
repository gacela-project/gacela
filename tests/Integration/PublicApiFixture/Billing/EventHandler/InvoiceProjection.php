<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PublicApiFixture\Billing\EventHandler;

/**
 * The near miss. `EventHandler` starts with the published segment `Event` and is
 * not it, so this stays internal -- on a prefix match every module's handlers
 * would be exported on a naming coincidence.
 */
final class InvoiceProjection
{
    public function project(): string
    {
        return 'projected';
    }
}
