<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleA;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleA\Service\ServiceA;

/**
 * @method ExampleAConfig getConfig()
 */
final class ExampleAFactory extends AbstractFactory
{
    public function createServiceA(): ServiceA
    {
        return new ServiceA();
    }
}
