<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    public function test_missing_factory_module(): void
    {
        $facade = new MissingFactoryFile\Facade();

        self::assertInstanceOf(AbstractFactory::class, $facade->getFactory());
    }

    public function test_missing_config_module(): void
    {
        $facade = new MissingConfigFile\Facade();

        self::assertInstanceOf(AbstractConfig::class, $facade->getConfig());
    }

    public function test_missing_provider_module(): void
    {
        $this->expectException(ProviderNotFoundException::class);

        $facade = new MissingProviderFile\Facade();
        $facade->error();
    }

    /**
     * A Facade+Factory-only module -- what `make:module --minimal` scaffolds --
     * has no Provider, but make() autowires by type and needs no bindings.
     */
    public function test_make_autowires_without_a_provider(): void
    {
        $factory = new MissingProviderFile\Factory();

        self::assertInstanceOf(
            MissingProviderFile\DomainService::class,
            $factory->makeDomainService(),
        );
    }
}
