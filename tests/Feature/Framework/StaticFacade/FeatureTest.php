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
        $greet = (new TestStaticFacade())->formalGreet('Jesus');

        self::assertSame('Hello, Jesus.', $greet);
    }

    public function test_non_existing_facade_method(): void
    {
        $this->expectExceptionMessage('Unknown method: unknownGreet');

        TestStaticFacade::unknownGreet('anything');
    }

    public function test_static_facade(): void
    {
        $greet = TestStaticFacade::informalGreet('Chema');

        self::assertSame('Hi, Chema!', $greet);
    }

    public function test_static_factory_from_facade(): void
    {
        $value = TestStaticFacade::factory()
            ->getConfig()
            ->getConfigValue();

        self::assertSame('config-value', $value);
    }
}
