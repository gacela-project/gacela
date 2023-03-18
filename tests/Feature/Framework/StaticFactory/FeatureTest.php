<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFactory;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\StaticFactory\Module\Config;
use GacelaTest\Feature\Framework\StaticFactory\Module\Factory as TestStaticFactory;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_object_factory(): void
    {
        $actual = (new TestStaticFactory())->createStringFromNonStaticFactory();

        self::assertSame(Config::STR, $actual);
    }

    public function test_static_factory(): void
    {
        $actual = TestStaticFactory::createStringFromStaticFactory();

        self::assertSame(Config::STR, $actual);
    }
}
