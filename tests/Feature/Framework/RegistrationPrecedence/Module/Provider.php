<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\RegistrationPrecedence\Module;

use ArrayObject;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    public const SERVICE = 'REGISTRATION_PRECEDENCE_SERVICE';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::SERVICE, new ArrayObject(['provider']));
    }
}
