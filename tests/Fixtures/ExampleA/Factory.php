<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleA;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleA\Service\ServiceA;

final class Factory extends AbstractFactory
{
    public function createServiceA(): ServiceA
    {
        return new ServiceA();
    }
}
