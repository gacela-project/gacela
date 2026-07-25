<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

/**
 * @psalm-type GraphEdge = array{from: string, to: string}
 */
final class GraphDiffResult
{
    /**
     * @param list<string> $addedModules
     * @param list<string> $removedModules
     * @param list<GraphEdge> $addedEdges
     * @param list<GraphEdge> $removedEdges
     */
    public function __construct(
        public readonly array $addedModules,
        public readonly array $removedModules,
        public readonly array $addedEdges,
        public readonly array $removedEdges,
    ) {
    }

    public function hasChanges(): bool
    {
        return $this->addedModules !== []
            || $this->removedModules !== []
            || $this->addedEdges !== []
            || $this->removedEdges !== [];
    }
}
