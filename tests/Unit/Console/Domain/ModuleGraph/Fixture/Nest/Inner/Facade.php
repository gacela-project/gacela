<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph\Fixture\Nest\Inner;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Unit\Console\Domain\ModuleGraph\Fixture\Nest\Inner\Domain\Service;

/**
 * Imports its own code, whose namespace is inside the Nest module's too.
 */
final class Facade extends AbstractFacade
{
    public function service(): string
    {
        return Service::class;
    }
}
