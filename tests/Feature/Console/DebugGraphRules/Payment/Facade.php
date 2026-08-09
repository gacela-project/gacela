<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraphRules\Payment;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Console\DebugGraphRules\Admin\Facade as AdminFacade;

/**
 * One half of a one-way edge: Payment reaches Admin, and Admin reaches nobody.
 * A rule saying that edge must not exist is what these fixtures are here for.
 *
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->createName();
    }

    public function reachesAdmin(): string
    {
        return AdminFacade::class;
    }
}
