<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Packaging;

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock\ClockInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock\SystemClock;

/**
 * What this application would ship if it were a package: the bindings a
 * consumer gets for free, and can replace without editing anything here.
 *
 * `addBindingIf()` is what makes that promise keepable. Reached through
 * `extendGacelaConfig()`, it runs against the same config instance the
 * application filled, so it can see that the clock is already bound and
 * decline -- whichever of the two lines came first.
 */
final class InvoicingPackageDefaults
{
    public function __invoke(GacelaConfig $config): void
    {
        $config->addBindingIf(ClockInterface::class, SystemClock::class);
    }
}
