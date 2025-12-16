<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Container;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Locator;
use Gacela\Framework\Exception\ServiceNotFoundException;
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

    public function test_get_required_throws_when_not_found(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('Service "GacelaTest\Unit\Framework\Container\NonExisting" not found in the container.');

        Locator::getInstance()->getRequired(NonExisting::class);
    }

    public function test_get_required_singleton_throws_when_not_found(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('Service "GacelaTest\Unit\Framework\Container\NonExisting" not found in the container.');

        Locator::getRequiredSingleton(NonExisting::class);
    }

    public function test_get_required_returns_existing_service(): void
    {
        Locator::addSingleton(StringValue::class, new StringValue('required'));

        $singleton = Locator::getInstance()->getRequired(StringValue::class);

        self::assertEquals(new StringValue('required'), $singleton);
    }

    public function test_get_required_singleton_returns_existing_service(): void
    {
        Locator::addSingleton(StringValue::class, new StringValue('required'));

        $singleton = Locator::getRequiredSingleton(StringValue::class);

        self::assertEquals(new StringValue('required'), $singleton);
    }

    public function test_get_required_singleton_from_container(): void
    {
        $container = new Container(bindings: [
            StringValue::class => new StringValue('from-container'),
        ]);

        $singleton = Locator::getRequiredSingleton(StringValue::class, $container);

        self::assertEquals(new StringValue('from-container'), $singleton);
    }
}
