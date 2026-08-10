<?php

declare(strict_types=1);

namespace Gacela\LaravelBridge;

use Gacela\Container\Attribute\Inject;
use Gacela\Framework\Gacela;
use Illuminate\Contracts\Container\Container;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

use ReflectionType;

use function is_object;
use function sprintf;

/**
 * Honors `#[Inject]` on properties and setters of Laravel-resolved services.
 *
 * The compiler pass covers Symfony because Symfony has definitions to rewrite;
 * Laravel builds classes it has never been told about, so the bridge listens
 * to `afterResolving` instead and injects into the instance Laravel just
 * built. Constructor parameters are {@see Attribute\Inject}'s job -- an
 * `afterResolving` listener arrives too late for a constructor.
 *
 * Either namespace of the attribute is honored (`IS_INSTANCEOF`, the same
 * contract Gacela's own resolver keeps), so a class annotated for Gacela
 * behaves the same when Laravel is the one building it.
 */
final class GacelaInjectListener
{
    /**
     * Class definitions cannot change within a process, so the plan survives
     * across applications on purpose -- most classes have no `#[Inject]`
     * member, and being told "none" once is enough.
     *
     * @var array<class-string, array{props: list<ReflectionProperty>, methods: list<ReflectionMethod>}|null>
     */
    private static array $plans = [];

    public static function register(Container $app): void
    {
        $app->afterResolving(static function (mixed $object): void {
            if (is_object($object)) {
                self::injectInto($object);
            }
        });
    }

    private static function injectInto(object $object): void
    {
        $plan = self::$plans[$object::class] ??= self::plan($object::class);
        if ($plan === null) {
            return;
        }

        foreach ($plan['props'] as $property) {
            self::injectProperty($object, $property);
        }

        foreach ($plan['methods'] as $method) {
            self::injectMethod($object, $method);
        }
    }

    /**
     * @param class-string $class
     *
     * @return array{props: list<ReflectionProperty>, methods: list<ReflectionMethod>}|null
     */
    private static function plan(string $class): ?array
    {
        $reflection = new ReflectionClass($class);

        $props = [];
        foreach (self::allProperties($reflection) as $property) {
            if ($property->getAttributes(Inject::class, ReflectionAttribute::IS_INSTANCEOF) !== []) {
                $props[] = $property;
            }
        }

        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getAttributes(Inject::class, ReflectionAttribute::IS_INSTANCEOF) !== []) {
                $methods[] = $method;
            }
        }

        if ($props === [] && $methods === []) {
            return null;
        }

        return ['props' => $props, 'methods' => $methods];
    }

    /**
     * A parent's private properties are invisible to the child's reflection,
     * and `#[Inject]` on one is no less a request for being inherited.
     *
     * @param ReflectionClass<object> $reflection
     *
     * @return list<ReflectionProperty>
     */
    private static function allProperties(ReflectionClass $reflection): array
    {
        $properties = $reflection->getProperties();

        for ($parent = $reflection->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            foreach ($parent->getProperties(ReflectionProperty::IS_PRIVATE) as $property) {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    private static function injectProperty(object $object, ReflectionProperty $property): void
    {
        if ($property->isStatic()) {
            throw new LogicException(sprintf(
                'Cannot #[Inject] the static property %s::$%s: injection targets an instance.',
                $property->getDeclaringClass()->getName(),
                $property->getName(),
            ));
        }

        if ($property->isReadOnly()) {
            throw new LogicException(sprintf(
                'Cannot #[Inject] the readonly property %s::$%s after construction: inject it through the constructor instead.',
                $property->getDeclaringClass()->getName(),
                $property->getName(),
            ));
        }

        // The target is computed before the initialized-check on purpose: an
        // untyped property is always initialized (implicitly, to null), so
        // checking value-state first would turn "this can never work" into
        // silence -- the failure the attribute exists to prevent.
        $target = self::propertyTarget($property);

        if ($property->isInitialized($object)) {
            return;
        }

        $property->setValue($object, Gacela::getRequired($target));
    }

    private static function injectMethod(object $object, ReflectionMethod $method): void
    {
        if ($method->isStatic() || !$method->isPublic()) {
            throw new LogicException(sprintf(
                'Cannot #[Inject] through %s::%s(): an injected method must be public and non-static.',
                $method->getDeclaringClass()->getName(),
                $method->getName(),
            ));
        }

        $attribute = $method->getAttributes(Inject::class, ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();

        $arguments = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $target = $attribute->implementation !== null && $method->getNumberOfParameters() === 1
                ? $attribute->implementation
                : self::assertClassType($type, $method);

            $arguments[] = Gacela::getRequired($target);
        }

        $method->invokeArgs($object, $arguments);
    }

    /**
     * @return class-string
     */
    private static function propertyTarget(ReflectionProperty $property): string
    {
        $attribute = $property->getAttributes(Inject::class, ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();
        if ($attribute->implementation !== null) {
            return $attribute->implementation;
        }

        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw new LogicException(sprintf(
                'Cannot #[Inject] the property %s::$%s: it needs a class type or an explicit #[Inject(SomeClass::class)].',
                $property->getDeclaringClass()->getName(),
                $property->getName(),
            ));
        }

        /** @var class-string */
        return $type->getName();
    }

    /**
     * @return class-string
     */
    private static function assertClassType(?ReflectionType $type, ReflectionMethod $method): string
    {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw new LogicException(sprintf(
                'Cannot #[Inject] through %s::%s(): every parameter needs a class type.',
                $method->getDeclaringClass()->getName(),
                $method->getName(),
            ));
        }

        /** @var class-string */
        return $type->getName();
    }
}
