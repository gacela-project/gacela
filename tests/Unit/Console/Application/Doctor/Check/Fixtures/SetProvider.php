<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use ArrayObject;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class SetProvider extends AbstractProvider
{
    public const ID = 'known.id';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::ID, new ArrayObject());
    }
}
