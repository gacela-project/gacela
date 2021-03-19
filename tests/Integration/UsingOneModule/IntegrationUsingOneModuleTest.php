<?php

declare(strict_types=1);

namespace GacelaTest\Integration\UsingOneModule;

use Gacela\Config;
use GacelaTest\Fixtures\ExampleModuleCalculator\ExampleModuleCalculatorFacade;
use PHPUnit\Framework\TestCase;

final class IntegrationUsingOneModuleTest extends TestCase
{
    public function setUp(): void
    {
        Config::$applicationRootDir = __DIR__;
        Config::init();
    }

    public function testCalculatorAdd(): void
    {
        $facade = new ExampleModuleCalculatorFacade();

        self::assertEquals(10, $facade->add(1, 2, 3, 4));
    }
}
