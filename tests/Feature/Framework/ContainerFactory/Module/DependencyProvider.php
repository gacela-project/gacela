<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerFactory\Module;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use GacelaTest\Fixtures\StringValue;

final class DependencyProvider extends AbstractDependencyProvider
{
    public const CACHED_SEED = 'CACHED_SEED';
    public const RANDOM_SEED = 'RANDOM_SEED';
    public const RANDOM_STRING_VALUE = 'RANDOM_STRING_VALUE';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addCacheSeed($container);
        $this->addRandomSeed($container);
        $this->addRandomStringValue($container);
    }

    private function addCacheSeed(Container $container): void
    {
        $container->set(
            self::CACHED_SEED,
            static fn () => (string)random_int(0, PHP_INT_MAX)
        );
    }

    private function addRandomSeed(Container $container): void
    {
        $container->set(
            self::RANDOM_SEED,
            $container->factory(static fn () => (string)random_int(0, PHP_INT_MAX))
        );
    }

    private function addRandomStringValue(Container $container): void
    {
        $container->set(
            self::RANDOM_STRING_VALUE,
            $container->factory(static function (Container $container) {
                /** @var string $cached */
                $cached = $container->get(self::CACHED_SEED);

                /** @var string $random */
                $random = $container->get(self::RANDOM_SEED);

                return new StringValue($cached . $random);
            })
        );
    }
}
