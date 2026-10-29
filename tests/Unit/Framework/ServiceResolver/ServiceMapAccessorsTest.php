<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ServiceResolver;

use Closure;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Gacela;
use Gacela\Framework\ServiceResolver\ServiceMapAccessors;
use GacelaTest\Unit\Framework\ServiceResolver\Fixtures\ChildWithoutAccessors;
use GacelaTest\Unit\Framework\ServiceResolver\Fixtures\ClassWithAccessors;
use GacelaTest\Unit\Framework\ServiceResolver\Fixtures\FirstService;
use GacelaTest\Unit\Framework\ServiceResolver\Fixtures\SecondService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class ServiceMapAccessorsTest extends TestCase
{
    public function test_an_accessor_resolves_to_the_class_the_attribute_names(): void
    {
        self::assertSame(
            FirstService::class,
            ServiceMapAccessors::classNameFor(new ReflectionClass(ClassWithAccessors::class), 'getFacade'),
        );
    }

    public function test_an_accessor_the_class_does_not_declare_resolves_to_nothing(): void
    {
        self::assertNull(
            ServiceMapAccessors::classNameFor(new ReflectionClass(ClassWithAccessors::class), 'getFactory'),
        );
    }

    public function test_a_class_declaring_nothing_resolves_to_nothing(): void
    {
        self::assertNull(ServiceMapAccessors::classNameFor(new ReflectionClass(stdClass::class), 'getFacade'));
        self::assertSame([], ServiceMapAccessors::declaredOn(new ReflectionClass(stdClass::class)));
    }

    public function test_every_declared_accessor_is_reported(): void
    {
        self::assertSame(
            ['getFacade' => FirstService::class, 'getConfig' => SecondService::class, 'getAbstractFactory' => AbstractFactory::class],
            ServiceMapAccessors::declaredOn(new ReflectionClass(ClassWithAccessors::class)),
        );
    }

    /**
     * Both readers have to answer a repeated accessor the same way, or the
     * analyser types the call the runtime does not make.
     */
    public function test_a_repeated_accessor_answers_the_first_declaration_in_both_readers(): void
    {
        $reflection = new ReflectionClass(ClassWithAccessors::class);

        self::assertSame(FirstService::class, ServiceMapAccessors::classNameFor($reflection, 'getFacade'));
        self::assertSame(FirstService::class, ServiceMapAccessors::declaredOn($reflection)['getFacade']);
        self::assertSame([FirstService::class, 'Facade'], ServiceMapAccessors::concretePillarFor(ClassWithAccessors::class, 'getFacade'));
    }

    #[DataProvider('fallbackMappings')]
    public function test_fallback_classification_preserves_the_raw_mapping(string $method, ?string $expectedClass): void
    {
        $reflection = new ReflectionClass(ClassWithAccessors::class);

        self::assertSame($expectedClass, ServiceMapAccessors::classNameFor($reflection, $method));
        self::assertNull(ServiceMapAccessors::concretePillarFor(ClassWithAccessors::class, $method));
        self::assertSame($expectedClass, ServiceMapAccessors::classNameFor($reflection, $method));
    }

    /**
     * @return iterable<string, array{string, ?class-string}>
     */
    public static function fallbackMappings(): iterable
    {
        yield 'abstract pillar' => ['getAbstractFactory', AbstractFactory::class];
        yield 'other class' => ['getConfig', SecondService::class];
        yield 'missing mapping' => ['getMissing', null];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_target_that_loads_later_is_classified_after_a_cache_reset(): void
    {
        self::assertFalse(class_exists(FirstService::class, false));
        $reflection = new ReflectionClass(ClassWithAccessors::class);
        self::assertSame(FirstService::class, ServiceMapAccessors::classNameFor($reflection, 'getFacade'));

        $this->withoutAutoloading(FirstService::class, static function (): void {
            self::assertNull(ServiceMapAccessors::concretePillarFor(ClassWithAccessors::class, 'getFacade'));
        });

        self::assertTrue(class_exists(FirstService::class));
        Gacela::resetCache();

        self::assertSame([FirstService::class, 'Facade'], ServiceMapAccessors::concretePillarFor(ClassWithAccessors::class, 'getFacade'));
    }

    /**
     * Attributes do not inherit, and the runtime reads the concrete class. A
     * reader that walked parents would type a call that resolves to nothing.
     */
    public function test_a_subclass_inherits_no_accessor(): void
    {
        $reflection = new ReflectionClass(ChildWithoutAccessors::class);

        self::assertNull(ServiceMapAccessors::classNameFor($reflection, 'getFacade'));
        self::assertSame([], ServiceMapAccessors::declaredOn($reflection));
        self::assertNull(ServiceMapAccessors::concretePillarFor(ChildWithoutAccessors::class, 'getFacade'));
    }

    private function withoutAutoloading(string $target, Closure $check): void
    {
        $autoloaders = spl_autoload_functions();
        $guard = static function (string $class) use ($target, $autoloaders): void {
            if ($class !== $target) {
                foreach ($autoloaders as $autoload) {
                    $autoload($class);
                }
            }
        };
        $this->replaceAutoloaders([$guard]);
        try {
            $check();
        } finally {
            $this->replaceAutoloaders($autoloaders);
        }
    }

    /**
     * @param list<callable> $autoloaders
     */
    private function replaceAutoloaders(array $autoloaders): void
    {
        foreach (spl_autoload_functions() as $autoload) {
            spl_autoload_unregister($autoload);
        }

        foreach ($autoloaders as $autoload) {
            spl_autoload_register($autoload);
        }
    }
}
