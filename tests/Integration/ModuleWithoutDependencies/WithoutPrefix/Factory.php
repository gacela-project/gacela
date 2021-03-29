<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleWithoutDependencies\WithoutPrefix;

use Gacela\AbstractFactory;
use GacelaTest\Integration\ModuleWithoutDependencies\WithoutPrefix\Service\HelloName;

final class Factory extends AbstractFactory
{
    public function createServiceA(): HelloName
    {
        return new HelloName();
    }
}
