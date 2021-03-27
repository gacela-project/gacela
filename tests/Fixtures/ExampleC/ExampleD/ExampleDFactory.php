<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\ExampleD;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleC\ExampleD\Service\ServiceD;

/**
 * @method ExampleDConfig getConfig()
 */
final class ExampleDFactory extends AbstractFactory
{
    public function createServiceA(): ServiceD
    {
        return new ServiceD();
    }
}
