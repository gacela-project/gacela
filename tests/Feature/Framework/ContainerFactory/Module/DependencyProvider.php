<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerFactory\Module;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use GacelaTest\Fixtures\StringValue;

final class DependencyProvider extends AbstractDependencyProvider
{
    public const RANDOM_STRING_VALUE = 'FACADE_NAME';

    private const RANDOM_SEED = 'RANDOM_SEED';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(
            self::RANDOM_SEED,
            $container->factory(static fn () => random_int(0, PHP_INT_MAX))
        );

        $container->set(
            self::RANDOM_STRING_VALUE,
            $container->factory(static function (Container $container) {
                /** @var int $random */
                $random = $container->get(self::RANDOM_SEED);

                return new StringValue('str_' . $random);
            })
        );
    }
}
