<?php

declare(strict_types=1);

namespace GacelaTest\Integration;

use Gacela\ClassResolver\Config\ConfigNotFoundException;
use Gacela\ClassResolver\Factory\FactoryNotFoundException;
use Gacela\Config;
use GacelaTest\Fixtures\ExampleA;
use GacelaTest\Fixtures\ExampleB;
use GacelaTest\Fixtures\ExampleC;
use GacelaTest\Fixtures\MissingConfigModule\MissingConfigModuleFacade;
use GacelaTest\Fixtures\MissingFactoryModule\MissingFactoryModuleFacade;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    /**
     * A module (ExampleA) in the root dir without any external module-dependencies.
     */
    public function testExampleA(): void
    {
        $facade = new ExampleA\Facade();

        self::assertEquals(
            ['Hello, Gacela from A.'],
            $facade->greet('Gacela')
        );
    }

    /**
     * A module (ExampleB) in the root dir with one module-dependency (ExampleA).
     */
    public function testExampleB(): void
    {
        $facade = new ExampleB\Facade();

        self::assertEquals(
            [
                'Hello, Gacela from A.',
                'Hello, Gacela from B.',
            ],
            $facade->greet('Gacela')
        );
    }

    /**
     * A module (ExampleC) in the root dir with multiple module-dependencies (ExampleA, ExampleB).
     */
    public function testExampleC(): void
    {
        $facade = new ExampleC\Facade();

        self::assertEquals(
            [
                '1',
                'Hello, Gacela from A.',
                'Hello, Gacela from A.',
                'Hello, Gacela from B.',
                'Hello, Gacela from C.',
                'result from a repository',
            ],
            $facade->greet('Gacela')
        );
    }

    public function testMissingFactoryModule(): void
    {
        $this->expectException(FactoryNotFoundException::class);

        $facade = new MissingFactoryModuleFacade();
        $facade->error();
    }

    public function testMissingConfigModule(): void
    {
        $this->expectException(ConfigNotFoundException::class);

        $facade = new MissingConfigModuleFacade();
        $facade->error();
    }
}
