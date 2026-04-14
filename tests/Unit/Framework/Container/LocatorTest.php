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

    public function test_get_required_suggests_similar_service_when_typo(): void
    {
        $container = new Container(bindings: [
            StringValue::class => new StringValue('registered'),
        ]);

        /** @var class-string $typo */
        $typo = 'GacelaTest\\Fixtures\\StringValu';

        try {
            Locator::getInstance($container)->getRequired($typo);
            self::fail('Expected ServiceNotFoundException');
        } catch (ServiceNotFoundException $serviceNotFoundException) {
            self::assertStringContainsString('Did you mean?', $serviceNotFoundException->getMessage());
            self::assertStringContainsString(StringValue::class, $serviceNotFoundException->getMessage());
        }
    }

    public function test_anonymous_global_takes_precedence_over_container_binding(): void
    {
        \Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal::resetCache();
        $fromGlobal = new StringValue('from-global');
        $fromContainer = new StringValue('from-container');

        \Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal::overrideExistingResolvedClass(
            StringValue::class,
            $fromGlobal,
        );

        $container = new Container(bindings: [
            StringValue::class => $fromContainer,
        ]);

        $resolved = Locator::getInstance($container)->get(StringValue::class);

        self::assertSame($fromGlobal, $resolved, 'AnonymousGlobal must be checked before container in Locator::get');
        \Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal::resetCache();
    }

    public function test_known_service_names_deduplicates_entries(): void
    {
        $container = new Container(bindings: [
            StringValue::class => new StringValue('registered'),
        ]);
        $container->set(StringValue::class, static fn (): StringValue => new StringValue('x'));

        /** @var class-string $typo */
        $typo = 'GacelaTest\\Fixtures\\StringValu';

        try {
            Locator::getInstance($container)->getRequired($typo);
            self::fail('Expected ServiceNotFoundException');
        } catch (ServiceNotFoundException $serviceNotFoundException) {
            self::assertSame(
                1,
                substr_count($serviceNotFoundException->getMessage(), StringValue::class),
                'Service name must appear only once in the suggestion list after dedup',
            );
        }
    }

    public function test_known_service_names_includes_both_registered_services_and_bindings(): void
    {
        // Fixtures\StringValue is only registered via set() (in getRegisteredServices),
        // while a separate binding key exists only in getBindings(). The suggestion
        // list must consider both sources.
        $bindingOnlyName = 'GacelaTest\\Fixtures\\OnlyInBinding';
        $container = new Container(bindings: [
            $bindingOnlyName => new StringValue('binding'),
        ]);
        $container->set(StringValue::class, static fn (): StringValue => new StringValue('registered'));

        /** @var class-string $typoOfRegistered */
        $typoOfRegistered = 'GacelaTest\\Fixtures\\StringValu';
        try {
            Locator::getInstance($container)->getRequired($typoOfRegistered);
            self::fail('Expected ServiceNotFoundException for registered-service typo');
        } catch (ServiceNotFoundException $serviceNotFoundException) {
            self::assertStringContainsString(StringValue::class, $serviceNotFoundException->getMessage());
        }
    }
}
