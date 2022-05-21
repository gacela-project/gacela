<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\AnonymousGlobalExtendsExistingClass;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, GacelaConfig::withDefaults());
    }

    public function test_override_existing_resolved_class_when_config_method_is_called(): void
    {
        AnonymousGlobal::overrideExistingResolvedClass(
            '\module-name@anonymous\FeatureTest\Config',
            new Module\Config()
        );

        AnonymousGlobal::overrideExistingResolvedClass(
            Module\Factory::class,
            new class() extends Module\Factory {
                public function createDomainService(): StringValue
                {
                    $this->getConfigValue();
                    return new StringValue('other');
                }
            }
        );

        $facade = new Module\Facade();
        $result = $facade->getSomething();

        self::assertSame('other', $result);
    }
}
