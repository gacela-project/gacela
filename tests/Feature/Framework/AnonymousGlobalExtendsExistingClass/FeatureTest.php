<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\AnonymousGlobalExtendsExistingClass;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

use function assert;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
        });
    }

    public function test_override_factory_as_anonymous_global(): void
    {
        AnonymousGlobal::overrideExistingResolvedClass(
            Module\Factory::class,
            new class() extends Module\Factory {
                public function createDomainService(): StringValue
                {
                    assert($this->getConfigValue() === 'value');
                    return new StringValue('other');
                }
            },
        );

        $facade = new Module\Facade();
        $result = $facade->getSomething();

        self::assertSame('other', $result);
    }

    public function test_override_config_as_anonymous_global(): void
    {
        AnonymousGlobal::overrideExistingResolvedClass(
            Module\Config::class,
            new class() extends Module\Config {
                public function getValue(): string
                {
                    return 'other';
                }
            },
        );

        $facade = new Module\Facade();
        $result = $facade->getSomething();

        self::assertSame('other', $result);
    }
}
