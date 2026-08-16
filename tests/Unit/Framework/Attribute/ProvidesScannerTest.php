<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\ProvidesScanner;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Exception\CircularProvidesException;
use GacelaTest\Unit\Framework\Attribute\Providers\CallCounter;
use GacelaTest\Unit\Framework\Attribute\Providers\NestedResolvingProvider;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderDefaultEmpty;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithAttributesOnly;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithContainerParam;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithMixedStyles;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithoutAttributes;
use GacelaTest\Unit\Framework\Attribute\Providers\ProviderWithPrivateAttribute;
use GacelaTest\Unit\Framework\Attribute\Providers\SelfReferentialProvider;
use GacelaTest\Unit\Framework\Attribute\Providers\SharedIdProviderOne;
use GacelaTest\Unit\Framework\Attribute\Providers\SharedIdProviderTwo;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

use function sprintf;

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

    public function test_only_container_typed_params_receive_the_container(): void
    {
        $container = new Container();

        ProvidesScanner::scan(new ProviderWithContainerParam(), $container);

        self::assertSame('untyped-ok', $container->get('untyped_param'));
        self::assertSame(7, $container->get('scalar_param'));
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

    public function test_second_scan_reuses_the_memoized_reflection_entries(): void
    {
        ProvidesScanner::scan(new ProviderWithAttributesOnly(), new Container());
        $memoizedEntries = $this->memoizedEntriesFor(ProviderWithAttributesOnly::class);

        ProvidesScanner::scan(new ProviderWithAttributesOnly(), new Container());

        self::assertSame($memoizedEntries, $this->memoizedEntriesFor(ProviderWithAttributesOnly::class));
    }

    public function test_register_is_final_to_protect_the_provides_scan(): void
    {
        // register() runs ProvidesScanner::scan(); overriding it would silently
        // disable #[Provides] scanning, so it must be final.
        self::assertTrue(
            (new ReflectionMethod(AbstractProvider::class, 'register'))->isFinal(),
        );
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

    /**
     * #870. Without the guard this is a `get()` that calls the method that
     * calls `get()`: on CLI it ends in PHP's own "Maximum call stack size ...
     * Infinite recursion?", an Error carrying a hundred thousand identical
     * frames and naming neither the provider nor the id.
     */
    public function test_a_provided_method_resolving_its_own_id_is_named_rather_than_overflowing_the_stack(): void
    {
        $container = new Container();
        ProvidesScanner::scan(new SelfReferentialProvider(), $container);

        $this->expectException(CircularProvidesException::class);
        $this->expectExceptionMessage(sprintf(
            '%s::selfReferential() is declared #[Provides(%s::class)] and its body resolves "%s" '
            . 'from the container, so providing it starts by providing it.',
            SelfReferentialProvider::class,
            SelfReferentialProvider::SELF_ID,
            SelfReferentialProvider::SELF_ID,
        ));

        $container->get(SelfReferentialProvider::SELF_ID);
    }

    public function test_a_self_referential_declaration_says_what_to_do_about_it(): void
    {
        $container = new Container();
        ProvidesScanner::scan(new SelfReferentialProvider(), $container);

        try {
            $container->get(SelfReferentialProvider::SELF_ID);
            self::fail('the self-referential declaration resolved');
        } catch (CircularProvidesException $circularProvidesException) {
            self::assertStringContainsString(
                'A provided method builds the service; it does not ask for the id it declares.',
                $circularProvidesException->getMessage(),
            );
        }
    }

    /**
     * A loop through a second id, which is the shape nothing about either
     * method reveals. The whole loop is named, closed back on the id it started
     * from, or the message reads as a path rather than a cycle.
     */
    public function test_a_cycle_across_two_provided_ids_names_the_whole_loop(): void
    {
        $container = new Container();
        ProvidesScanner::scan(new SelfReferentialProvider(), $container);

        $this->expectException(CircularProvidesException::class);
        $this->expectExceptionMessage(sprintf(
            'Resolving "%s" leads back to itself: %s (%s::indirect) -> %s (%s::sound) -> %s.',
            SelfReferentialProvider::INDIRECT_ID,
            SelfReferentialProvider::INDIRECT_ID,
            SelfReferentialProvider::class,
            SelfReferentialProvider::SOUND_ID,
            SelfReferentialProvider::class,
            SelfReferentialProvider::INDIRECT_ID,
        ));

        $container->get(SelfReferentialProvider::INDIRECT_ID);
    }

    /**
     * The wiring the guard must not break: one provided method resolving
     * another is ordinary, and nests the stack two deep every time.
     */
    public function test_a_provided_method_may_resolve_another_provided_id(): void
    {
        $container = new Container();
        ProvidesScanner::scan(new NestedResolvingProvider(), $container);

        self::assertSame('inner+outer', $container->get(NestedResolvingProvider::OUTER_ID));
    }

    /**
     * Two modules may legitimately provide the same id -- each answers for
     * itself, which is why `DuplicateProvidedIdCheck` looks per Provider. One
     * resolving the other's puts that id on the stack twice, and a guard keyed
     * on the id alone would call it a cycle.
     */
    public function test_the_same_id_provided_by_two_providers_is_not_a_cycle(): void
    {
        $otherModule = new Container();
        ProvidesScanner::scan(new SharedIdProviderTwo(), $otherModule);

        $container = new Container();
        ProvidesScanner::scan(new SharedIdProviderOne($otherModule), $container);

        self::assertSame('from-one+from-two', $container->get(SharedIdProviderOne::SHARED_ID));
    }

    /**
     * The frame is popped in a `finally`, so the failure does not leave the id
     * marked as resolving for everything that follows in the process.
     */
    public function test_a_reported_cycle_leaves_nothing_on_the_resolution_stack(): void
    {
        $container = new Container();
        ProvidesScanner::scan(new SelfReferentialProvider(), $container);

        try {
            $container->get(SelfReferentialProvider::SELF_ID);
        } catch (CircularProvidesException) {
            // The assertion below is the point.
        }

        self::assertSame([], $this->resolutionStack());
    }

    public function test_a_successful_provide_leaves_nothing_on_the_resolution_stack(): void
    {
        $container = new Container();
        ProvidesScanner::scan(new NestedResolvingProvider(), $container);

        $container->get(NestedResolvingProvider::OUTER_ID);

        self::assertSame([], $this->resolutionStack());
    }

    /**
     * @return list<array{id: string, provider: class-string, method: string}>
     */
    private function resolutionStack(): array
    {
        /** @var list<array{id: string, provider: class-string, method: string}> $stack */
        $stack = (new ReflectionProperty(ProvidesScanner::class, 'resolving'))->getValue();

        return $stack;
    }

    /**
     * The memo is only observable through its own storage: a re-scan rebuilds
     * entries that compare equal but hold fresh ReflectionMethod instances.
     *
     * @param class-string $provider
     *
     * @return list<array{id: string, method: ReflectionMethod, needsContainer: bool}>
     */
    private function memoizedEntriesFor(string $provider): array
    {
        /** @var array<class-string, list<array{id: string, method: ReflectionMethod, needsContainer: bool}>> $cache */
        $cache = (new ReflectionProperty(ProvidesScanner::class, 'cache'))->getValue();

        return $cache[$provider];
    }
}
