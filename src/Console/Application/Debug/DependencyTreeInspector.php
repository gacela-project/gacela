<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

use Gacela\Container\DependencyNode as ContainerDependencyNode;
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
 *
 * Both views come off one `dependencyGraph()` call: the flat list of classes
 * reached, and the shape that says who reached them. Deriving the flat list
 * from the graph rather than asking twice is what keeps them from disagreeing.
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
        $graph = $container->dependencyGraph($className);

        $nodes = [];
        foreach ($graph->flatten() as $dependency) {
            $nodes[] = new DependencyNode($dependency, $this->classify($container, $bindings, $dependency));
        }

        return new DependencyTreeInspection(
            $className,
            $nodes,
            $this->toTree($container, $bindings, $graph->children),
        );
    }

    /**
     * The root itself is dropped: it is what was asked about, not something it
     * depends on -- the same reason `flatten()` leaves it out.
     *
     * @param array<string, mixed> $bindings
     * @param list<ContainerDependencyNode> $children
     *
     * @return list<DependencyTreeNode>
     */
    private function toTree(Container $container, array $bindings, array $children): array
    {
        $nodes = [];

        foreach ($children as $child) {
            $nodes[] = new DependencyTreeNode(
                $child->className,
                $child->parameter,
                $this->classify($container, $bindings, $child->className),
                $this->toTree($container, $bindings, $child->children),
                $child->repeated,
            );
        }

        return $nodes;
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
