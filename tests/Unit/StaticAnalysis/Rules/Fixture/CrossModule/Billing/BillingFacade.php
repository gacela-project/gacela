<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing;

use Gacela\Framework\AbstractFacade;

/**
 * The module's entry point: naming it from another module is allowed.
 */
final class BillingFacade extends AbstractFacade
{
}
