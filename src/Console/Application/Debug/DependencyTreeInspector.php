<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Exception\GacelaNotBootstrappedException;
use Gacela\Framework\Gacela;

use function class_exists;

/**
 * Reports the transitive dependencies of a class as the container sees them.
 *
 * {@see ConstructorInspector} re-derives one level of the answer from type
 * hints; this asks the container for the tree it will actually walk, so
 * bindings, contextual bindings and attributes are already accounted for.
 */
final class DependencyTreeInspector
{
    /**
     * @param class-string $className
     */
    public function inspect(string $className): DependencyTreeInspection
    {
        $container = $this->container();
        if (!$container instanceof Container) {
            return new DependencyTreeInspection($className, [], containerAvailable: false);
        }

        $bindings = $container->getBindings();
        $nodes = [];

        foreach ($container->getDependencyTree($className) as $dependency) {
            $nodes[] = new DependencyNode($dependency, $this->classify($container, $bindings, $dependency));
        }

        return new DependencyTreeInspection($className, $nodes);
    }

    /**
     * The merged bindings map is read once by the caller and passed down: it
     * merges every parent container's map on each call, so building it per node
     * would make inspecting a scope scale with the size of its parent.
     *
     * @param array<string, mixed> $bindings
     */
    private function classify(Container $container, array $bindings, string $id): ProvisionStatus
    {
        if (isset($bindings[$id])) {
            return ProvisionStatus::Binding;
        }

        // Deliberately not has(), which is true of anything instantiable and so
        // cannot tell a stored instance from a class nobody registered.
        if ($container->provides($id)) {
            return ProvisionStatus::Instance;
        }

        return class_exists($id)
            ? ProvisionStatus::Autowired
            : ProvisionStatus::Unresolvable;
    }

    private function container(): ?Container
    {
        try {
            return Gacela::container();
        } catch (GacelaNotBootstrappedException) {
            return null;
        }
    }
}
