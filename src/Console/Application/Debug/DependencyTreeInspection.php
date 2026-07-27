<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

final class DependencyTreeInspection
{
    /**
     * @param class-string $className
     * @param list<DependencyNode> $nodes
     * @param bool $containerAvailable false when Gacela was never bootstrapped,
     *        which is a different answer from "this class has no dependencies"
     */
    public function __construct(
        public readonly string $className,
        public readonly array $nodes,
        public readonly bool $containerAvailable = true,
    ) {
    }

    /**
     * @return list<DependencyNode>
     */
    public function unresolvableNodes(): array
    {
        $result = [];
        foreach ($this->nodes as $node) {
            if (!$node->isProvided()) {
                $result[] = $node;
            }
        }

        return $result;
    }

    public function isFullyProvided(): bool
    {
        return $this->unresolvableNodes() === [];
    }
}
