<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
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

    public function test_missing_dependency_provider_module(): void
    {
        $this->expectException(ProviderNotFoundException::class);

        $facade = new MissingProviderFile\Facade();
        $facade->error();
    }
}
