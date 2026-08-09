<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;

use function array_values;

/**
 * Every class a class refers to, however the reference is written.
 *
 * A module reaches another module through more than the names written in
 * expressions: a constructor type-hint, a return type, an attribute, a caught
 * exception and an extended base class are all dependencies, and all of them
 * cost the file an import. The module graph is built from imports, so a rule
 * checked per class has to look at the same set or the CLI and the editor would
 * disagree about what depends on what.
 */
final class ReferencedClasses
{
    /**
     * @return list<string> fully qualified, de-duplicated, in the order first seen
     */
    public static function in(ClassLike $node): array
    {
        $names = [];

        foreach (self::nameNodes($node) as $name) {
            $resolved = ResolvedName::of($name);
            $names[$resolved] = $resolved;
        }

        return array_values($names);
    }

    /**
     * @return array<Name>
     */
    private static function nameNodes(ClassLike $node): array
    {
        $names = self::declarationNames($node);

        foreach ((new NodeFinder())->find($node, static fn (Node $n): bool => self::carriesReferences($n)) as $found) {
            foreach (self::referencesOf($found) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * What the class header itself names -- the one place the references are not
     * found by walking the body.
     *
     * @return array<Name>
     */
    private static function declarationNames(ClassLike $node): array
    {
        if ($node instanceof Class_) {
            return [...($node->extends instanceof Name ? [$node->extends] : []), ...$node->implements];
        }

        if ($node instanceof Interface_) {
            return $node->extends;
        }

        return $node instanceof Enum_ ? $node->implements : [];
    }

    private static function carriesReferences(Node $node): bool
    {
        return $node instanceof Node\Expr\New_
            || $node instanceof Node\Expr\StaticCall
            || $node instanceof Node\Expr\StaticPropertyFetch
            || $node instanceof Node\Expr\ClassConstFetch
            || $node instanceof Node\Expr\Instanceof_
            || $node instanceof Node\Stmt\TraitUse
            || $node instanceof Node\Stmt\Catch_
            || $node instanceof Node\Attribute
            || $node instanceof Node\Param
            || $node instanceof Node\Stmt\Property
            || $node instanceof Node\FunctionLike;
    }

    /**
     * @return array<Name>
     */
    private static function referencesOf(Node $node): array
    {
        if ($node instanceof Node\Stmt\TraitUse) {
            return $node->traits;
        }

        if ($node instanceof Node\Stmt\Catch_) {
            return $node->types;
        }

        if ($node instanceof Node\Attribute) {
            return [$node->name];
        }

        if ($node instanceof Node\Expr\New_
            || $node instanceof Node\Expr\StaticCall
            || $node instanceof Node\Expr\StaticPropertyFetch
            || $node instanceof Node\Expr\ClassConstFetch
            || $node instanceof Node\Expr\Instanceof_
        ) {
            // `new $class` / `$class::CONST` name nothing to match on.
            return $node->class instanceof Name ? [$node->class] : [];
        }

        if ($node instanceof Node\Param) {
            return self::typeNames($node->type);
        }

        if ($node instanceof Node\Stmt\Property) {
            return self::typeNames($node->type);
        }

        return $node instanceof Node\FunctionLike ? self::typeNames($node->getReturnType()) : [];
    }

    /**
     * @return array<Name>
     */
    private static function typeNames(null|ComplexType|Identifier|Name $type): array
    {
        if ($type instanceof Name) {
            return [$type];
        }

        if ($type instanceof NullableType) {
            return self::typeNames($type->type);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $names = [];
            foreach ($type->types as $inner) {
                foreach (self::typeNames($inner) as $name) {
                    $names[] = $name;
                }
            }

            return $names;
        }

        // An Identifier is a builtin -- `string`, `int`, `never` -- and names no class.
        return [];
    }
}
