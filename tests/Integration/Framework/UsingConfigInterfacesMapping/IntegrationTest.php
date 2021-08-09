<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigInterfacesMapping;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::init(__DIR__, ['isWorking?' => 'yes!']);
    }

    public function test_mapping_interfaces_from_config(): void
    {
        $facade = new LocalConfig\Facade();

        self::assertSame(
            'Hello Gacela! Name: Chemaclass & Jesus',
            $facade->generateCompanyAndName()
        );
    }
}
