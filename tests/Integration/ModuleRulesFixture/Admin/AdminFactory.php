<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleRulesFixture\Admin;

use Gacela\Framework\AbstractFactory;

final class AdminFactory extends AbstractFactory
{
    public function createName(): string
    {
        return 'admin';
    }
}
