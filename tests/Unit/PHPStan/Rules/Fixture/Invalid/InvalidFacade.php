<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\Invalid;

use Gacela\Framework\AbstractFacade;

/**
 * Makes the namespace a module, so the misnamed pillars beside it are reported.
 */
final class InvalidFacade extends AbstractFacade
{
}
