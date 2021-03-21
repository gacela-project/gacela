<?php

declare(strict_types=1);

namespace GacelaTest\Integration;

use Gacela\Config;
use GacelaTest\Fixtures\ExampleA\ExampleAFacade;
use GacelaTest\Fixtures\ExampleB\ExampleBFacade;
use GacelaTest\Fixtures\ExampleC\ExampleCFacade;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function testExampleA(): void
    {
        $facade = new ExampleAFacade();

        self::assertEquals(
            ['Hello, Gacela from A.'],
            $facade->greet('Gacela')
        );
    }

    public function testExampleB(): void
    {
        $facade = new ExampleBFacade();

        self::assertEquals(
            [
                'Hello, Gacela from A.',
                'Hello, Gacela from B.',
            ],
            $facade->greet('Gacela')
        );
    }

    public function testExampleC(): void
    {
        $facade = new ExampleCFacade();

        self::assertEquals(
            [
                '1',
                'Hello, Gacela from A.',
                'Hello, Gacela from A.',
                'Hello, Gacela from B.',
                'Hello, Gacela from C.',
            ],
            $facade->greet('Gacela')
        );
    }
}
