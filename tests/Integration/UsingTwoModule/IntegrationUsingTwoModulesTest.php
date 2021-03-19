<?php

declare(strict_types=1);

namespace GacelaTest\Integration\UsingTwoModule;

use Gacela\Config;
use GacelaTest\Fixtures\ExampleModuleGreeting\ExampleModuleGreetingFacade;
use PHPUnit\Framework\TestCase;

final class IntegrationUsingTwoModulesTest extends TestCase
{
    public function setUp(): void
    {
        Config::$applicationRootDir = __DIR__;
        Config::init();
    }

    public function testGreetingWithASum(): void
    {
        $facade = new ExampleModuleGreetingFacade();

        self::assertEquals('Hello, Gacela! 2 + 2 = 4', $facade->greet('Gacela'));
    }
}
