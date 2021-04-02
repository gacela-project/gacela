<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ModuleWithExternalDependencies\Dependent;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\ModuleWithExternalDependencies\Dependent\Service\HelloName;

final class Factory extends AbstractFactory
{
    public function createServiceA(): HelloName
    {
        return new HelloName();
    }
}
