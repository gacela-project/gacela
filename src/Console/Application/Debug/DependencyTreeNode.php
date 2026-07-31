<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

/**
 * One class in the dependency tree, keeping the shape the flat
 * {@see DependencyNode} list throws away.
 *
 * The flat list answers "what does this touch, and can the container provide
 * it". This answers "how" -- which constructor parameter pulled the class in,
 * how deep it sits, and where a cycle closes. Both are wanted at once: a
 * missing dependency is only actionable once you know who asked for it.
 */
final class DependencyTreeNode
{
    /**
     * @param string $className the concrete class, after bindings are resolved
     * @param string|null $parameter the constructor parameter this satisfies in its parent
     * @param list<DependencyTreeNode> $children one per constructor parameter that takes a class
     * @param bool $repeated the class is already its own ancestor here, so the
     *        graph was cut rather than recursed into
     */
    public function __construct(
        public readonly string $className,
        public readonly ?string $parameter,
        public readonly ProvisionStatus $status,
        public readonly array $children = [],
        public readonly bool $repeated = false,
    ) {
    }

    public function isProvided(): bool
    {
        return $this->status->isProvided();
    }
}
