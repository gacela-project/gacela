<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\StaticFacade\ModuleA\Facade as TestStaticFacade;
use GacelaTest\Feature\Framework\StaticFacade\ModuleA\Factory as StaticFactory;
use GacelaTest\Feature\Framework\StaticFacade\ModuleB\Facade as TestObjectFacade;
use GacelaTest\Feature\Framework\StaticFacade\ModuleB\Factory as ObjectFactory;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_module_a_static_facade(): void
    {
        $actual = TestStaticFacade::createString();

        self::assertSame(StaticFactory::STR, $actual);
    }

    public function test_module_a_object_facade(): void
    {
        $actual = (new TestObjectFacade())->createString();

        self::assertSame(ObjectFactory::STR, $actual);
    }
}
