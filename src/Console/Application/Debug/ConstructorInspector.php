<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

use Gacela\Framework\Gacela;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

use Throwable;

use function class_exists;
use function interface_exists;
use function is_callable;
use function is_object;
use function sprintf;
use function var_export;

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
     * @param array<class-string, class-string|callable|object> $bindings
     */
    private function inspectParameter(ReflectionParameter $parameter, array $bindings): ParameterInspection
    {
        $type = $parameter->getType();
        $name = '$' . $parameter->getName();
        $renderedType = $this->renderType($type);

        $inject = $this->readInject($parameter);
        if ($inject !== null) {
            return new ParameterInspection($name, $renderedType, ParameterStatus::Inject, $inject);
        }

        if ($type === null) {
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

    private function renderType(?ReflectionType $type): string
    {
        if (!$type instanceof ReflectionType) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
        }

        return (string) $type;
    }

    private function defaultDetail(ReflectionParameter $parameter): string
    {
        /** @var mixed $default */
        $default = $parameter->getDefaultValue();
        return sprintf('= %s', var_export($default, true));
    }

    /**
     * @param class-string|callable|object $target
     */
    private function renderBindingTarget(mixed $target): string
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
     * @return array<class-string, class-string|callable|object>
     */
    private function containerBindings(): array
    {
        try {
            return Gacela::container()->getBindings();
        } catch (Throwable) {
            return [];
        }
    }

    private function readInject(ReflectionParameter $parameter): ?string
    {
        $attributes = $parameter->getAttributes(\Gacela\Container\Attribute\Inject::class);
        if ($attributes === []) {
            return null;
        }

        $inject = $attributes[0]->newInstance();
        return $inject->implementation !== null
            ? sprintf('inject -> %s', $inject->implementation)
            : 'inject';
    }
}
