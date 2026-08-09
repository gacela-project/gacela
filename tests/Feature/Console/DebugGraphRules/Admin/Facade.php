<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraphRules\Admin;

use Gacela\Framework\AbstractFacade;

/**
 * The other half: reached by Payment, reaching nobody. See Payment\Facade.
 *
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->createName();
    }
}
