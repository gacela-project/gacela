<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

use Gacela\Console\Domain\FileContent\PhpValue;

use Gacela\Container\Attribute\Inject;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Exception\GacelaNotBootstrappedException;
use Gacela\Framework\Gacela;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

use function class_exists;
use function interface_exists;
use function is_callable;
use function is_object;
use function sprintf;

/**
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 */
final class ConstructorInspector
{
    /**
     * @param class-string $className
     */
    public function inspect(string $className): ConstructorInspection
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new ConstructorInspection($className, false, []);
        }

        $bindings = $this->containerBindings();
        $parameters = [];

        foreach ($constructor->getParameters() as $parameter) {
            $parameters[] = $this->inspectParameter($parameter, $bindings);
        }

        return new ConstructorInspection($className, true, $parameters);
    }

    /**
     * @param BindingsMap $bindings
     */
    private function inspectParameter(ReflectionParameter $parameter, array $bindings): ParameterInspection
    {
        $type = $parameter->getType();
        $name = '$' . $parameter->getName();
        $renderedType = $this->renderType($type);

        $inject = $this->readInject($parameter);

        if (!$inject instanceof InjectedImplementation) {
            return $this->inspectByType($parameter, $type, $name, $renderedType, $bindings);
        }

        // `#[Inject(RedisCache::class)]` names what to inject, so that class
        // decides. A name nothing declares is the same fault as a type that
        // does not exist -- and is caught nowhere else, since the container
        // reads the name only when it builds.
        if ($inject->exists !== null) {
            return $inject->exists
                ? new ParameterInspection($name, $renderedType, ParameterStatus::Inject, $inject->detail)
                : new ParameterInspection($name, $renderedType, ParameterStatus::MissingType, $inject->detail);
        }

        // A bare `#[Inject]` names nothing: the container resolves the
        // parameter's own type, exactly as it would without the attribute. This
        // used to return here regardless, which made the attribute read as proof
        // that something could supply the type -- so `--check` passed for an
        // unbound interface that throws `DependencyNotFoundException` the moment
        // the module is built. It labels the answer below; it is not the answer.
        $inspection = $this->inspectByType($parameter, $type, $name, $renderedType, $bindings);

        return $inspection->isResolvable()
            ? new ParameterInspection($name, $renderedType, ParameterStatus::Inject, $inject->detail)
            : $inspection;
    }

    /**
     * @param BindingsMap $bindings
     */
    private function inspectByType(
        ReflectionParameter $parameter,
        ?ReflectionType $type,
        string $name,
        string $renderedType,
        array $bindings,
    ): ParameterInspection {
        if (!$type instanceof ReflectionType) {
            return $parameter->isDefaultValueAvailable()
                ? new ParameterInspection($name, $renderedType, ParameterStatus::HasDefault, $this->defaultDetail($parameter))
                : new ParameterInspection($name, $renderedType, ParameterStatus::NoTypeHint, 'no type hint and no default');
        }

        if (!$type instanceof ReflectionNamedType) {
            return new ParameterInspection($name, $renderedType, ParameterStatus::UnsupportedType, 'union/intersection types not inspected');
        }

        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return new ParameterInspection($name, $renderedType, ParameterStatus::HasDefault, $this->defaultDetail($parameter));
            }

            return new ParameterInspection($name, $renderedType, ParameterStatus::ScalarWithoutDefault, 'scalar without default');
        }

        if (isset($bindings[$typeName])) {
            return new ParameterInspection(
                $name,
                $renderedType,
                ParameterStatus::Bound,
                sprintf('bound -> %s', $this->renderBindingTarget($bindings[$typeName])),
            );
        }

        if (class_exists($typeName)) {
            return new ParameterInspection($name, $renderedType, ParameterStatus::Autowirable, 'autowirable');
        }

        if (interface_exists($typeName)) {
            return new ParameterInspection($name, $renderedType, ParameterStatus::UnboundInterface, 'interface, no binding');
        }

        return new ParameterInspection($name, $renderedType, ParameterStatus::MissingType, 'type does not exist');
    }

    /**
     * ReflectionType::__toString() already renders every shape the way this
     * command wants it: `?Foo` for a nullable named type, `mixed` (never
     * `?mixed`) for mixed, and `A|B` / `A&B` for union and intersection types.
     */
    private function renderType(?ReflectionType $type): string
    {
        return $type instanceof ReflectionType
            ? (string) $type
            : 'mixed';
    }

    private function defaultDetail(ReflectionParameter $parameter): string
    {
        /** @var mixed $default */
        $default = $parameter->getDefaultValue();
        return sprintf('= %s', PhpValue::export($default));
    }

    /**
     * @param callable|class-string|object $target
     */
    private function renderBindingTarget(string|object|callable $target): string
    {
        if (is_object($target)) {
            return $target::class . ' instance';
        }

        if (is_callable($target)) {
            return 'callable';
        }

        return $target;
    }

    /**
     * @return BindingsMap
     */
    private function containerBindings(): array
    {
        try {
            return Gacela::container()->getBindings();
        } catch (GacelaNotBootstrappedException) {
            return [];
        }
    }

    /**
     * What `#[Inject]` says about this parameter, or null when it is absent.
     */
    private function readInject(ReflectionParameter $parameter): ?InjectedImplementation
    {
        // IS_INSTANCEOF, so the framework's `Inject`, which subclasses this one to
        // re-present it under `Gacela\Framework`, reports the same. An exact match
        // would show an injected parameter as plain autowiring.
        $attributes = $parameter->getAttributes(Inject::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attributes === []) {
            return null;
        }

        $implementation = $attributes[0]->newInstance()->implementation;

        if ($implementation === null) {
            return new InjectedImplementation('inject', null);
        }

        return new InjectedImplementation(
            sprintf('inject -> %s', $implementation),
            class_exists($implementation),
        );
    }
}
