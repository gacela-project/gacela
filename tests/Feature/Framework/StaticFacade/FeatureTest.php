<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\StaticFacade\Module\Facade as TestStaticFacade;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_object_facade(): void
    {
        $actual = (new TestStaticFacade())->formalGreet('Jesus');

        self::assertSame('Hello, Jesus.', $actual);
    }

    public function test_static_facade(): void
    {
        $actual = TestStaticFacade::informalGreet('Chema');

        self::assertSame('Hi, Chema!', $actual);
    }

    public function test_static_facade_shares_factory(): void
    {
        $f1 = TestStaticFacade::factory();
        $f2 = TestStaticFacade::factory();

        self::assertSame($f1, $f2);
    }

    public function test_static_factory_from_facade(): void
    {
        $actual = TestStaticFacade::factory()
            ->getConfig()
            ->getConfigValue();

        self::assertSame('config-value', $actual);
    }
}
