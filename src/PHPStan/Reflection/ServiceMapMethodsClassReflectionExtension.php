<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Reflection;

use Gacela\Framework\ServiceResolver\ServiceMap;
use PHPStan\Reflection\Annotations\AnnotationMethodReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\ObjectType;
use ReflectionAttribute;

use function class_exists;
use function interface_exists;

/**
 * Types `getFacade()`/`getFactory()`/`getConfig()` from the `#[ServiceMap]`
 * attribute that already declares them.
 *
 * Without this, those calls are undefined methods that have to be silenced, and
 * a silenced call is not a typed one: it degrades to `mixed`, which stops the
 * analyser checking the entire chain behind the accessor, not just the accessor.
 * The attribute already carries the method name and the class it resolves to.
 *
 * The runtime reads `#[ServiceMap]` off the concrete class only -- attributes do
 * not inherit -- so this deliberately does not walk parents. Being more generous
 * than the resolver would type-check calls that fail at runtime, which is worse
 * than the suppression it replaces.
 *
 * On AnnotationMethodReflection: PHPStan covers neither implementing
 * ExtendedMethodReflection nor constructing this class under its backward
 * compatibility promise -- `@api` on the interface means it is safe to *call*,
 * not safe to implement. Both options can break on a PHPStan minor, so the
 * tiebreaker is which one PHPStan keeps in step with its own interface: this is
 * their canonical implementation of it, updated whenever the interface grows,
 * whereas a hand-rolled one silently becomes abstract-incomplete and fatals in
 * every consumer's CI. It also spares us re-deriving 27 methods of semantics.
 */
final class ServiceMapMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $this->mappedClass($classReflection, $methodName) !== null;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): ExtendedMethodReflection
    {
        $mappedClass = $this->mappedClass($classReflection, $methodName);
        if ($mappedClass === null) {
            throw new ServiceMapMethodNotFoundException($classReflection->getName(), $methodName);
        }

        $returnType = new ObjectType($mappedClass);

        return new AnnotationMethodReflection(
            $methodName,
            $classReflection,
            $returnType,
            [],
            false,
            false,
            null,
            TemplateTypeMap::createEmpty(),
        );
    }

    /**
     * @return class-string|null
     */
    private function mappedClass(ClassReflection $classReflection, string $methodName): ?string
    {
        $nativeReflection = $classReflection->getNativeReflection();

        // Asking the ClassReflection would re-enter this extension and recurse;
        // the magic dispatch only exists if the class really has __call.
        if (!$nativeReflection->hasMethod('__call')) {
            return null;
        }

        foreach ($nativeReflection->getAttributes(ServiceMap::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            /** @var ServiceMap $serviceMap */
            $serviceMap = $attribute->newInstance();

            if ($serviceMap->method !== $methodName) {
                continue;
            }

            // A mapping to a class that does not exist resolves to nothing at
            // runtime either; typing it would only move the failure.
            if (!class_exists($serviceMap->className) && !interface_exists($serviceMap->className)) {
                return null;
            }

            return $serviceMap->className;
        }

        return null;
    }
}
