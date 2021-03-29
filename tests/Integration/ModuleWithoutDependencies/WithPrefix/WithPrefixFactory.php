<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleWithoutDependencies\WithPrefix;

use Gacela\AbstractFactory;
use GacelaTest\Integration\ModuleWithoutDependencies\WithPrefix\Service\HelloName;

final class WithPrefixFactory extends AbstractFactory
{
    public function createServiceA(): HelloName
    {
        return new HelloName();
    }
}
