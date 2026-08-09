<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleRulesFixture\Admin;

use Gacela\Framework\AbstractFacade;

/**
 * The module the rules file says Payment must not reach.
 *
 * Well-formed on purpose: the always-on pillar rules run over this fixture too,
 * and a finding from one of those would say nothing about the rule under test.
 *
 * @extends AbstractFacade<AdminFactory>
 */
final class AdminFacade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->createName();
    }
}
