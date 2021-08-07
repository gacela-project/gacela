<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigInterfacesMapping;

use Gacela\Framework\Config;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Config::getInstance()->init(__DIR__);
    }

    public function test_remove_key_from_container(): void
    {
        $facade = new LocalConfig\Facade();

        self::assertSame(
            'Hello Gacela! Name: Chemaclass & Jesus',
            $facade->generateCompanyAndName()
        );
    }
}
