<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<CleanFactory>
 */
final class LogicFacade extends AbstractFacade
{
    public function doesArithmetic(): int
    {
        return 1 + 1;
    }
}
