<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleWithExternalDependencies\Dependent;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\ModuleWithExternalDependencies\Dependent\Service\HelloName;

final class Factory extends AbstractFactory
{
    public function createServiceA(): HelloName
    {
        return new HelloName();
    }
}
