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
     * The other half of the Provider story. `getProvidedDependency()` raises
     * `ProviderNotFoundException` only when the module has no Provider at all;
     * with a Provider that registers nothing, it falls through to the container
     * and a key nobody provided comes back as `null`.
     *
     * Pinned rather than endorsed. `get()` returning null for an unknown id is
     * the container's call -- `getOrFail()` is the one that throws -- but the
     * silent null reaches the application through Gacela's own API, which is
     * why this belongs here and not only upstream.
     */
    public function test_a_provided_dependency_nobody_registered_comes_back_null(): void
    {
        $facade = new MissingContainerServiceKey\Facade();

        self::assertNull($facade->domainService());
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
