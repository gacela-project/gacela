<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleA2;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleA2\Service\ServiceA;

/**
 * @method Config getConfig()
 */
final class Factory extends AbstractFactory
{
    public function createServiceA(): ServiceA
    {
        return new ServiceA();
    }
}
