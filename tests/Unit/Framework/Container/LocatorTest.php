<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Container;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Locator;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

final class LocatorTest extends TestCase
{
    protected function setUp(): void
    {
        Locator::resetInstance();
    }

    public function test_get_concrete_class(): void
    {
        /** @var StringValue $stringValue */
        $stringValue = Locator::getInstance()->get(StringValue::class);
        self::assertInstanceOf(StringValue::class, $stringValue);
        self::assertSame('', $stringValue->value());
        $stringValue->setValue('updated value');

        /** @var StringValue $stringValue2 */
        $stringValue2 = Locator::getInstance()->get(StringValue::class);
        self::assertSame('updated value', $stringValue2->value());
    }

    public function test_get_non_existing_singleton(): void
    {
        $nullValue = Locator::getSingleton(NonExisting::class);

        self::assertNull($nullValue);
    }

    public function test_get_existing_singleton(): void
    {
        Locator::addSingleton(StringValue::class, new StringValue('str'));

        $singleton = Locator::getSingleton(StringValue::class);

        self::assertEquals(new StringValue('str'), $singleton);
    }

    public function test_get_existing_singleton_from_container(): void
    {
        $container = new Container(bindings: [
            StringValue::class => new StringValue('str'),
        ]);

        $singleton = Locator::getSingleton(StringValue::class, $container);

        self::assertEquals(new StringValue('str'), $singleton);
    }
}
