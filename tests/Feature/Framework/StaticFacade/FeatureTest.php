<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade;

use Error;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\StaticFacade\ModuleA\Facade as TestFacade;
use GacelaTest\Feature\Framework\StaticFacade\ModuleA\Factory as TestFactory;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_unknown_facade_method(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Call to undefined method ' . TestFacade::class . '::unknown()');

        (new TestFacade())->unknown();
    }

    public function test_facade_can_create_string(): void
    {
        $facade = new TestFacade();
        $actual = $facade->createString();

        self::assertSame(TestFactory::STR, $actual);
    }

    public function test_factory_access_is_explicit(): void
    {
        $facade = new TestFacade();
        $actual = $facade->getFactory()->createString();

        self::assertSame(TestFactory::STR, $actual);
    }
}
