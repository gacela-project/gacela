<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ReadOnlyAppRoot;

use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<Config>
 */
final class Factory extends AbstractFactory
{
    public function createGreetingService(): GreetingService
    {
        return new GreetingService();
    }
}
