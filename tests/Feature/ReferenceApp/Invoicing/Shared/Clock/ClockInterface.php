<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock;

/**
 * The one thing the application asks of its host: what time it is.
 *
 * Supplied through `GacelaConfig::addExternalService()` at bootstrap, so the
 * modules below depend on this interface and never on how the host tells time.
 */
interface ClockInterface
{
    /**
     * The current date, as `YYYY-MM-DD`.
     */
    public function today(): string;
}
