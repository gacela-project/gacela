<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\StaticFacade\ModuleA\Facade as TestStaticFacadeA;
use GacelaTest\Feature\Framework\StaticFacade\ModuleA\Factory as FactoryA;
use GacelaTest\Feature\Framework\StaticFacade\ModuleB\Facade as TestStaticFacadeB;
use GacelaTest\Feature\Framework\StaticFacade\ModuleB\Factory as FactoryB;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_module_a_object_facade(): void
    {
        $actual = (new TestStaticFacadeA())->createStringFromNonStaticFactory();

        self::assertSame(FactoryA::STR, $actual);
    }

    public function test_module_a_static_facade(): void
    {
        $actual = TestStaticFacadeA::createStringFromStaticFactory();

        self::assertSame(FactoryA::STR, $actual);
    }

    public function test_module_b_object_facade(): void
    {
        $actual = (new TestStaticFacadeB())->createStringFromNonStaticFactory();

        self::assertSame(FactoryB::STR, $actual);
    }

    public function test_module_b_static_facade(): void
    {
        $actual = TestStaticFacadeB::createStringFromStaticFactory();

        self::assertSame(FactoryB::STR, $actual);
    }
}
