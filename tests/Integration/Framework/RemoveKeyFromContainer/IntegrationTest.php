<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RemoveKeyFromContainer;

use Gacela\Framework\Config;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Config::getInstance()->init(__DIR__);
    }

    public function test_remove_key_from_container(): void
    {
        $this->expectException(ContainerKeyNotFoundException::class);

        $facade = new AddAndRemoveKey\Facade();
        $facade->doSomething();
    }
}
