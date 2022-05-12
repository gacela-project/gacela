<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static fn (GacelaConfig $config) => $config
            ->addExternalService('isWorking?', 'yes!'));
    }

    public function test_mapping_interfaces_from_config(): void
    {
        self::assertSame(
            'Hello Gacela! Name: Chemaclass & Jesus',
            (new LocalConfig\Facade())->generateCompanyAndName()
        );
    }
}
