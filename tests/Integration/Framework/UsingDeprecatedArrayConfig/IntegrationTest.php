<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingDeprecatedArrayConfig;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function test_deprecated_mapping_interfaces_from_container(): void
    {
        $this->expectDeprecation();
        Gacela::bootstrap(__DIR__);

        $facade = new LocalConfig\Facade();

        self::assertSame(
            'Hello Gacela! Name: Chemaclass & Jesus',
            $facade->generateCompanyAndName()
        );
    }
}
