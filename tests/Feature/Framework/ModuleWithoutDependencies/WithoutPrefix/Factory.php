<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithoutDependencies\WithoutPrefix;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\ModuleWithoutDependencies\WithoutPrefix\Service\HelloName;

final class Factory extends AbstractFactory
{
    public function createServiceA(): HelloName
    {
        return new HelloName();
    }
}
