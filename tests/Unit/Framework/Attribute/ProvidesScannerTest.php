<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute;

use Gacela\Framework\Attribute\ProvidesScanner;
use Gacela\Framework\Container\Container;
use GacelaTest\Unit\Framework\Attribute\Providers\CallCounter;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderDefaultEmpty;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithAttributesOnly;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithContainerParam;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithMixedStyles;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithoutAttributes;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithPrivateAttribute;
use PHPUnit\Framework\TestCase;

final class ProvidesScannerTest extends TestCase
{
    public function test_registers_each_provides_annotated_method_under_its_id(): void
    {
        $container = new Container();

        ProvidesScanner::scan(new ProviderWithAttributesOnly(), $container);

        self::assertSame('hello', $container->get('string_service'));
        self::assertSame([1, 2, 3], $container->get('list_service'));
    }

    public function test_ignores_methods_without_the_attribute(): void
    {
        $container = new Container();

        ProvidesScanner::scan(new ProviderWithAttributesOnly(), $container);

        self::assertFalse($container->has('withoutAttribute'));
    }

    public function test_ignores_non_public_methods(): void
    {
        $container = new Container();

        ProvidesScanner::scan(new ProviderWithPrivateAttribute(), $container);

        self::assertTrue($container->has('public_one'));
        self::assertFalse($container->has('private_one'));
    }

    public function test_does_not_invoke_the_method_until_resolved(): void
    {
        $counter = new CallCounter();
        $provider = new ProviderWithAttributesOnly($counter);
        $container = new Container();

        ProvidesScanner::scan($provider, $container);

        self::assertSame(0, $counter->count, 'scan() must be lazy');

        $container->get('counted_service');

        self::assertSame(1, $counter->count);
    }

    public function test_passes_container_to_methods_that_declare_it(): void
    {
        $container = new Container();

        ProvidesScanner::scan(new ProviderWithContainerParam(), $container);

        self::assertSame(Container::class, $container->get('container_class'));
        self::assertSame('no-container', $container->get('paramless'));
    }

    public function test_works_on_provider_without_any_attribute(): void
    {
        $container = new Container();

        ProvidesScanner::scan(new ProviderWithoutAttributes(), $container);
        ProvidesScanner::scan(new ProviderDefaultEmpty(), $container);

        self::assertSame([], $container->getRegisteredServices());
    }

    public function test_cache_survives_across_instances_of_the_same_class(): void
    {
        $containerA = new Container();
        $containerB = new Container();

        ProvidesScanner::scan(new ProviderWithAttributesOnly(), $containerA);
        ProvidesScanner::scan(new ProviderWithAttributesOnly(), $containerB);

        self::assertSame('hello', $containerA->get('string_service'));
        self::assertSame('hello', $containerB->get('string_service'));
    }

    public function test_register_combines_attributes_with_manual_provides(): void
    {
        $container = new Container();

        (new ProviderWithMixedStyles())->register($container);

        self::assertSame('from-attribute', $container->get(ProviderWithMixedStyles::ATTRIBUTE_ID));
        self::assertSame('from-manual', $container->get(ProviderWithMixedStyles::MANUAL_ID));
    }

    public function test_register_on_provider_without_attributes_preserves_manual_wiring(): void
    {
        $container = new Container();

        (new ProviderWithoutAttributes())->register($container);

        self::assertSame('manual-only', $container->get(ProviderWithoutAttributes::MANUAL_ID));
    }

    public function test_register_on_bare_provider_is_a_noop(): void
    {
        $container = new Container();

        (new ProviderDefaultEmpty())->register($container);

        self::assertSame([], $container->getRegisteredServices());
    }
}
