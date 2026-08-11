<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection;

use Gacela\PHPStan\Reflection\ServiceMapMethodNotFoundException;
use Gacela\PHPStan\Reflection\ServiceMapMethodsClassReflectionExtension;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\MappedFacade;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\MappedFactory;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\ServiceMapToMissingClass;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\ServiceMapWithoutMagicCall;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\WithoutServiceMap;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\WithPositionalServiceMap;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\WithRepeatedServiceMap;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\WithSameMethodDifferentClass;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\WithServiceMap;
use Override;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;

final class ServiceMapMethodsClassReflectionExtensionTest extends PHPStanTestCase
{
    private ServiceMapMethodsClassReflectionExtension $extension;

    #[Override]
    protected function setUp(): void
    {
        $this->extension = new ServiceMapMethodsClassReflectionExtension(self::createReflectionProvider());
    }

    public function test_declares_the_method_named_by_the_attribute(): void
    {
        self::assertTrue($this->extension->hasMethod($this->reflect(WithServiceMap::class), 'getFacade'));
    }

    public function test_does_not_declare_a_method_no_attribute_names(): void
    {
        self::assertFalse($this->extension->hasMethod($this->reflect(WithServiceMap::class), 'getConfig'));
    }

    public function test_returns_the_mapped_class_as_the_return_type(): void
    {
        $method = $this->extension->getMethod($this->reflect(WithServiceMap::class), 'getFacade');

        self::assertSame('getFacade', $method->getName());
        self::assertSame(
            MappedFacade::class,
            $method->getOnlyVariant()->getReturnType()->getObjectClassNames()[0],
        );
    }

    public function test_the_method_takes_no_parameters(): void
    {
        $method = $this->extension->getMethod($this->reflect(WithServiceMap::class), 'getFacade');

        self::assertSame([], $method->getOnlyVariant()->getParameters());
        self::assertFalse($method->getOnlyVariant()->isVariadic());
    }

    public function test_the_method_is_public_and_not_static(): void
    {
        $method = $this->extension->getMethod($this->reflect(WithServiceMap::class), 'getFacade');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isPrivate());
        self::assertFalse($method->isStatic());
        self::assertSame(WithServiceMap::class, $method->getDeclaringClass()->getName());
    }

    public function test_positional_attribute_arguments_are_read_too(): void
    {
        $classReflection = $this->reflect(WithPositionalServiceMap::class);

        self::assertTrue($this->extension->hasMethod($classReflection, 'getConfig'));
        self::assertSame(
            MappedFacade::class,
            $this->extension->getMethod($classReflection, 'getConfig')
                ->getOnlyVariant()->getReturnType()->getObjectClassNames()[0],
        );
    }

    public function test_a_class_without_the_attribute_is_left_alone(): void
    {
        self::assertFalse($this->extension->hasMethod($this->reflect(WithoutServiceMap::class), 'getFacade'));
    }

    /**
     * The attribute alone resolves nothing: without `__call` there is no
     * dispatch, so claiming the method exists would type-check a fatal error.
     */
    public function test_the_attribute_without_magic_call_declares_nothing(): void
    {
        self::assertFalse($this->extension->hasMethod($this->reflect(ServiceMapWithoutMagicCall::class), 'getFacade'));
    }

    public function test_an_attribute_pointing_at_a_missing_class_declares_nothing(): void
    {
        self::assertFalse($this->extension->hasMethod($this->reflect(ServiceMapToMissingClass::class), 'getFacade'));
    }

    public function test_asking_for_an_undeclared_method_throws(): void
    {
        $this->expectException(ServiceMapMethodNotFoundException::class);
        $this->expectExceptionMessage(
            'No #[ServiceMap] on "' . WithServiceMap::class . '" declares the method "getConfig".',
        );

        $this->extension->getMethod($this->reflect(WithServiceMap::class), 'getConfig');
    }

    public function test_repeated_lookups_agree(): void
    {
        $classReflection = $this->reflect(WithServiceMap::class);

        self::assertTrue($this->extension->hasMethod($classReflection, 'getFacade'));
        self::assertTrue($this->extension->hasMethod($classReflection, 'getFacade'));
        self::assertFalse($this->extension->hasMethod($classReflection, 'nope'));
        self::assertFalse($this->extension->hasMethod($classReflection, 'nope'));
    }

    /**
     * PHPStan asks hasMethod() before getMethod(), and both land here, so the
     * attribute is read twice for every accessor unless the answer is kept.
     */
    public function test_a_resolved_accessor_is_read_once_per_class_and_method(): void
    {
        $reflectionProvider = $this->createMock(ReflectionProvider::class);
        $reflectionProvider->expects(self::once())
            ->method('hasClass')
            ->with(MappedFacade::class)
            ->willReturn(true);

        $extension = new ServiceMapMethodsClassReflectionExtension($reflectionProvider);
        $classReflection = $this->reflect(WithServiceMap::class);

        self::assertTrue($extension->hasMethod($classReflection, 'getFacade'));
        self::assertSame(
            MappedFacade::class,
            $extension->getMethod($classReflection, 'getFacade')
                ->getOnlyVariant()->getReturnType()->getObjectClassNames()[0],
        );
    }

    /**
     * Two modules naming their accessor the same way is the normal case, not an
     * edge one: a kept answer that forgets which class asked would serve one
     * module's Facade to every other.
     */
    public function test_the_same_accessor_on_another_class_keeps_its_own_mapping(): void
    {
        self::assertSame(
            MappedFacade::class,
            $this->extension->getMethod($this->reflect(WithServiceMap::class), 'getFacade')
                ->getOnlyVariant()->getReturnType()->getObjectClassNames()[0],
        );
        self::assertSame(
            MappedFactory::class,
            $this->extension->getMethod($this->reflect(WithSameMethodDifferentClass::class), 'getFacade')
                ->getOnlyVariant()->getReturnType()->getObjectClassNames()[0],
        );
    }

    /**
     * PHPStan analyses classes it never loads. Asking the autoloader instead
     * would drop the mapping for any of them, silently untyping the accessor.
     */
    public function test_the_mapped_class_is_looked_up_in_phpstan_not_the_autoloader(): void
    {
        $reflectionProvider = $this->createStub(ReflectionProvider::class);
        $reflectionProvider->method('hasClass')->willReturn(true);

        $extension = new ServiceMapMethodsClassReflectionExtension($reflectionProvider);

        self::assertFalse(class_exists('GacelaTest\Unit\PHPStan\Reflection\Fixture\ThisClassDoesNotExist'));
        self::assertTrue($extension->hasMethod($this->reflect(ServiceMapToMissingClass::class), 'getFacade'));
    }

    /**
     * The attribute is repeatable and a module maps several pillars, so a
     * non-matching attribute must not end the scan.
     */
    public function test_every_repeated_attribute_is_scanned_not_just_the_first(): void
    {
        $classReflection = $this->reflect(WithRepeatedServiceMap::class);

        self::assertSame(
            MappedFacade::class,
            $this->extension->getMethod($classReflection, 'getFacade')
                ->getOnlyVariant()->getReturnType()->getObjectClassNames()[0],
        );
        self::assertSame(
            MappedFactory::class,
            $this->extension->getMethod($classReflection, 'getFactory')
                ->getOnlyVariant()->getReturnType()->getObjectClassNames()[0],
        );
    }

    public function test_exposes_exactly_one_variant(): void
    {
        $method = $this->extension->getMethod($this->reflect(WithServiceMap::class), 'getFacade');

        self::assertCount(1, $method->getVariants());
        self::assertNull($method->getNamedArgumentsVariants());
        self::assertSame(
            MappedFacade::class,
            $method->getVariants()[0]->getReturnType()->getObjectClassNames()[0],
        );
    }

    public function test_the_accessor_is_not_reported_as_deprecated_or_internal(): void
    {
        $method = $this->extension->getMethod($this->reflect(WithServiceMap::class), 'getFacade');

        self::assertTrue($method->isDeprecated()->no());
        self::assertTrue($method->isInternal()->no());
    }

    /**
     * @param class-string $className
     */
    private function reflect(string $className): ClassReflection
    {
        return self::createReflectionProvider()->getClass($className);
    }
}
