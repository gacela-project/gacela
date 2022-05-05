<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingGacelaConfigFn;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\CustomClass;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        $setup = (new SetupGacela())
            ->setExternalServices(['CustomClassFromExternalService' => CustomClass::class]);

        Gacela::bootstrap(__DIR__, $setup);
    }

    public function test_config_php_files(): void
    {
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'config' => 1,
                'override' => 2,
                'local' => 3,
                'override_from_local' => 4,
            ],
            $facade->doSomething()
        );
    }
}
