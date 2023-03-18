<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\StaticFacade\Module\Facade as TestStaticFacade;
use GacelaTest\Feature\Framework\StaticFacade\Module\Factory;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_object_facade(): void
    {
        $actual = (new TestStaticFacade())->createStringFromNonStaticFactory();

        self::assertSame(Factory::STR, $actual);
    }

    public function test_static_facade(): void
    {
        $actual = TestStaticFacade::createStringFromStaticFactory();

        self::assertSame(Factory::STR, $actual);
    }
}
