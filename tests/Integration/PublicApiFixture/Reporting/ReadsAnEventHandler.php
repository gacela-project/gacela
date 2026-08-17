<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PublicApiFixture\Reporting;

use GacelaTest\Integration\PublicApiFixture\Billing\EventHandler\InvoiceProjection;

/**
 * `EventHandler` merely starts with the published segment `Event`, so this
 * crossing is reported in every configuration -- the naming coincidence must not
 * export a module's internals.
 */
final class ReadsAnEventHandler
{
    public function __construct(
        private readonly InvoiceProjection $projection,
    ) {
    }

    public function report(): string
    {
        return $this->projection->project();
    }
}
