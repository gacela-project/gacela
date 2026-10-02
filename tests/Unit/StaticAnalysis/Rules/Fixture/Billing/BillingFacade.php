<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules\Fixture\Billing;

use Gacela\Framework\AbstractFacade;

/**
 * Makes this namespace a module: the resolver starts from a Facade.
 */
final class BillingFacade extends AbstractFacade
{
}
