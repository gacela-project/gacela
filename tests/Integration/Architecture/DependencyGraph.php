<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_filter;
use function array_keys;
use function array_pop;
use function array_values;
use function count;
use function file_get_contents;
use function implode;
use function in_array;
use function min;
use function sort;
use function str_starts_with;
use function strrpos;
use function substr;

/**
 * Builds the intra-`src/` dependency graph and reports its cycles.
 *
 * The graph comes from the parsed AST rather than from `use` statements: a class
 * referencing another class in the *same* namespace needs no import, so an
 * import-based graph silently drops every same-namespace edge — around a quarter
 * of this tree — and reports far smaller cycles than actually exist.
 *
 * Every reference that creates a compile- or run-time coupling is an edge:
 * extends, implements, trait use, new, static calls, class constants, `::class`,
 * instanceof, catch, attributes, and parameter/return/property types.
 */
final class DependencyGraph
{
    /**
     * @param array<string, list<string>> $classEdges
     * @param array<string, list<string>> $namespaceEdges
     */
    private function __construct(
        private readonly array $classEdges,
        private readonly array $namespaceEdges,
    ) {
    }

    public static function fromDirectory(string $directory): self
    {
        $collector = self::collect($directory);
        $classEdges = self::internalEdges($collector);

        return new self($classEdges, self::aggregateToNamespaces($classEdges));
    }

    /**
     * Each cycle is the sorted list of its members joined by ' | '.
     *
     * @return list<string>
     */
    public function classCycles(): array
    {
        return self::cyclesOf($this->classEdges);
    }

    /**
     * @return list<string>
     */
    public function namespaceCycles(): array
    {
        return self::cyclesOf($this->namespaceEdges);
    }

    /**
     * @param array<string, list<string>> $edges
     *
     * @return list<string>
     */
    private static function cyclesOf(array $edges): array
    {
        $cycles = [];

        foreach (self::stronglyConnectedComponents($edges) as $component) {
            if (count($component) < 2) {
                continue;
            }
            sort($component);
            $cycles[] = implode(' | ', $component);
        }
        sort($cycles);

        return $cycles;
    }

    private static function collect(string $directory): NodeVisitorAbstract
    {
        $collector = new class() extends NodeVisitorAbstract {
            /** @var array<string, list<string>> */
            public array $edges = [];

            /** @var array<string, true> */
            public array $defined = [];

            /** @var list<string> */
            private array $stack = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\ClassLike) {
                    $this->enterClassLike($node);

                    return null;
                }

                $current = $this->current();
                if ($current === null) {
                    return null;
                }

                foreach (self::referencesOf($node) as $reference) {
                    $this->addEdge($current, $reference);
                }

                return null;
            }

            public function leaveNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\ClassLike && $node->namespacedName !== null) {
                    array_pop($this->stack);
                }

                return null;
            }

            /**
             * @return list<string>
             */
            private static function referencesOf(Node $node): array
            {
                if ($node instanceof Node\Stmt\TraitUse) {
                    return self::names($node->traits);
                }
                if ($node instanceof Node\Stmt\Catch_) {
                    return self::names($node->types);
                }
                if ($node instanceof Node\Attribute) {
                    return [$node->name->toString()];
                }
                if ($node instanceof Node\Expr\New_
                    || $node instanceof Node\Expr\StaticCall
                    || $node instanceof Node\Expr\StaticPropertyFetch
                    || $node instanceof Node\Expr\ClassConstFetch
                    || $node instanceof Node\Expr\Instanceof_
                ) {
                    return $node->class instanceof Node\Name ? [$node->class->toString()] : [];
                }
                if ($node instanceof Node\Param) {
                    return self::typeNames($node->type);
                }
                if ($node instanceof Node\Stmt\Property) {
                    return self::typeNames($node->type);
                }
                if ($node instanceof Node\FunctionLike) {
                    return self::typeNames($node->getReturnType());
                }

                return [];
            }

            /**
             * @param array<Node\Name> $names
             *
             * @return list<string>
             */
            private static function names(array $names): array
            {
                $out = [];
                foreach ($names as $name) {
                    $out[] = $name->toString();
                }

                return $out;
            }

            /**
             * @return list<string>
             */
            private static function typeNames(?Node $type): array
            {
                if ($type instanceof Node\Name) {
                    return [$type->toString()];
                }
                if ($type instanceof Node\NullableType) {
                    return self::typeNames($type->type);
                }
                if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
                    $out = [];
                    foreach ($type->types as $inner) {
                        foreach (self::typeNames($inner) as $name) {
                            $out[] = $name;
                        }
                    }

                    return $out;
                }

                return [];
            }

            private function enterClassLike(Node\Stmt\ClassLike $node): void
            {
                if ($node->namespacedName === null) {
                    return;
                }

                $fqn = $node->namespacedName->toString();
                $this->stack[] = $fqn;
                $this->defined[$fqn] = true;
                $this->edges[$fqn] ??= [];

                if ($node instanceof Node\Stmt\Class_ && $node->extends instanceof Node\Name) {
                    $this->addEdge($fqn, $node->extends->toString());
                }
                if ($node instanceof Node\Stmt\Interface_) {
                    foreach (self::names($node->extends) as $name) {
                        $this->addEdge($fqn, $name);
                    }
                }
                if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Enum_) {
                    foreach (self::names($node->implements) as $name) {
                        $this->addEdge($fqn, $name);
                    }
                }
            }

            private function current(): ?string
            {
                return $this->stack === [] ? null : $this->stack[count($this->stack) - 1];
            }

            private function addEdge(string $from, string $to): void
            {
                if ($from === $to || !str_starts_with($to, 'Gacela\\')) {
                    return;
                }
                if (in_array($to, $this->edges[$from] ?? [], true)) {
                    return;
                }
                $this->edges[$from][] = $to;
            }
        };

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());
            $traverser->addVisitor($collector);
            $traverser->traverse($parser->parse((string) file_get_contents($file->getPathname())) ?? []);
        }

        return $collector;
    }

    /**
     * Drops references to classes outside `src/` (vendor, PHP core) so the graph
     * only contains edges this repository can actually change.
     *
     * @return array<string, list<string>>
     */
    private static function internalEdges(NodeVisitorAbstract $collector): array
    {
        /** @var array<string, list<string>> $rawEdges */
        $rawEdges = $collector->edges;
        /** @var array<string, true> $defined */
        $defined = $collector->defined;

        $edges = [];
        foreach ($rawEdges as $from => $targets) {
            $edges[$from] = array_values(array_filter(
                $targets,
                static fn (string $to): bool => isset($defined[$to]),
            ));
        }

        return $edges;
    }

    /**
     * @param array<string, list<string>> $classEdges
     *
     * @return array<string, list<string>>
     */
    private static function aggregateToNamespaces(array $classEdges): array
    {
        $edges = [];

        foreach ($classEdges as $from => $targets) {
            $fromNamespace = self::namespaceOf($from);
            $edges[$fromNamespace] ??= [];

            foreach ($targets as $to) {
                $toNamespace = self::namespaceOf($to);
                if ($fromNamespace === $toNamespace || in_array($toNamespace, $edges[$fromNamespace], true)) {
                    continue;
                }
                $edges[$fromNamespace][] = $toNamespace;
            }
        }

        return $edges;
    }

    private static function namespaceOf(string $fqn): string
    {
        $position = strrpos($fqn, '\\');

        return $position === false ? $fqn : substr($fqn, 0, $position);
    }

    /**
     * Tarjan's strongly-connected-components algorithm (iterative).
     *
     * @param array<string, list<string>> $edges
     *
     * @return list<list<string>>
     */
    private static function stronglyConnectedComponents(array $edges): array
    {
        $index = [];
        $low = [];
        $onStack = [];
        $stack = [];
        $result = [];
        $counter = 0;

        foreach (array_keys($edges) as $start) {
            if (isset($index[$start])) {
                continue;
            }

            // Iterative DFS: each frame tracks the node and how far through its edges we are.
            $work = [[$start, 0]];

            while ($work !== []) {
                [$node, $edgePointer] = $work[count($work) - 1];

                if ($edgePointer === 0) {
                    $index[$node] = $counter;
                    $low[$node] = $counter;
                    ++$counter;
                    $stack[] = $node;
                    $onStack[$node] = true;
                }

                $recursed = false;
                $neighbors = $edges[$node] ?? [];
                for ($i = $edgePointer; $i < count($neighbors); ++$i) {
                    $next = $neighbors[$i];
                    if (!isset($edges[$next])) {
                        continue;
                    }
                    if (!isset($index[$next])) {
                        $work[count($work) - 1] = [$node, $i + 1];
                        $work[] = [$next, 0];
                        $recursed = true;
                        break;
                    }
                    if (($onStack[$next] ?? false) === true) {
                        $low[$node] = min($low[$node], $index[$next]);
                    }
                }

                if ($recursed) {
                    continue;
                }

                if ($low[$node] === $index[$node]) {
                    $component = [];
                    do {
                        $w = array_pop($stack);
                        $onStack[$w] = false;
                        $component[] = $w;
                    } while ($w !== $node);
                    $result[] = $component;
                }

                array_pop($work);
                if ($work !== []) {
                    $parent = $work[count($work) - 1][0];
                    $low[$parent] = min($low[$parent], $low[$node]);
                }
            }
        }

        return $result;
    }
}
