<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\StaticFacade\ModuleA\Facade as TestStaticFacade;
use GacelaTest\Feature\Framework\StaticFacade\ModuleA\Factory as StaticFactory;
use GacelaTest\Feature\Framework\StaticFacade\ModuleB\Facade as TestObjectFacade;
use GacelaTest\Feature\Framework\StaticFacade\ModuleB\Factory as ObjectFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_unknown_static_facade_method(): void
    {
        $this->expectExceptionMessage("Method unknown: 'unknown'");

        TestStaticFacade::unknown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_unknown_object_factory_method(): void
    {
        $this->expectExceptionMessage("Method unknown: 'unknown'");

        (new TestObjectFacade())->unknown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_unknown_static_factory_method(): void
    {
        $this->expectExceptionMessage("Method unknown: 'innerUnknownFacadeMethod'");

        TestStaticFacade::unknownFacadeMethod();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_module_a_static_facade(): void
    {
        $actual = TestStaticFacade::createString();

        self::assertSame(StaticFactory::STR, $actual);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_module_a_object_facade(): void
    {
        $actual = (new TestObjectFacade())->createString();

        self::assertSame(ObjectFactory::STR, $actual);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_factory_static_facade_method(): void
    {
        $actual = TestStaticFacade::getFactory()->createString();

        self::assertSame(StaticFactory::STR, $actual);
    }
}
