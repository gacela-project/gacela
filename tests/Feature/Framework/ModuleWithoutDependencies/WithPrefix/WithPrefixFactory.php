<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithoutDependencies\WithPrefix;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\ModuleWithoutDependencies\WithPrefix\Service\HelloName;

final class WithPrefixFactory extends AbstractFactory
{
    public function createServiceA(): HelloName
    {
        return new HelloName();
    }
}
