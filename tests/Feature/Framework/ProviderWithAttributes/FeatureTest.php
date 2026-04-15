<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ProviderWithAttributes;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ProviderWithAttributes\Greeter\Facade;
use GacelaTest\Feature\Framework\ProviderWithAttributes\Greeter\Provider;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    public function test_provider_registers_services_via_attributes(): void
    {
        $facade = new Facade();

        self::assertSame('Hello Gacela (2026-04-15)', $facade->greet('Gacela'));
    }

    public function test_attribute_provider_returns_list_service(): void
    {
        $facade = new Facade();

        self::assertSame(['Hello', 'Hola', 'Bonjour'], $facade->prefixes());
    }

    public function test_attribute_method_receives_container_when_typed(): void
    {
        $provider = new Provider();
        $container = new Container();

        $provider->register($container);

        self::assertSame(Container::class, $container->get(Provider::LOCATOR_CHECK));
    }
}
