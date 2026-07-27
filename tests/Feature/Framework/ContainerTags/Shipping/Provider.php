<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerTags\Shipping;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    public const VALIDATORS = 'SHIPPING_VALIDATORS';

    public const TAG = 'validators';

    public function provideModuleDependencies(Container $container): void
    {
        $container->tag(AddressValidator::class, self::TAG);

        $container->set(
            self::VALIDATORS,
            static fn (): array => [...$container->tagged(self::TAG)],
        );
    }
}
