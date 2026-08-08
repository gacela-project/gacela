<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Double;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RuntimeException;

use function sprintf;

/**
 * Turns a snippet into the AST node an analyser is handed.
 *
 * Both hosts build their nodes with the same library, so a node parsed here is
 * the same thing PHPStan and Psalm pass in -- which is what makes a host-free
 * test of a rule meaningful rather than a rehearsal.
 */
final class ParseSource
{
    public static function classIn(string $php): ClassLike
    {
        $node = (new NodeFinder())->findFirstInstanceOf(self::parse($php), ClassLike::class);

        if (!$node instanceof ClassLike) {
            throw new RuntimeException('The snippet declares no class');
        }

        return $node;
    }

    public static function methodIn(string $php, string $methodName): ClassMethod
    {
        foreach (self::classIn($php)->getMethods() as $method) {
            if ($method->name->toString() === $methodName) {
                return $method;
            }
        }

        throw new RuntimeException(sprintf('The snippet declares no method %s()', $methodName));
    }

    /**
     * @return list<\PhpParser\Node\Stmt>
     */
    private static function parse(string $php): array
    {
        $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse($php);

        if ($statements === null) {
            throw new RuntimeException('The snippet is not parseable php');
        }

        return $statements;
    }
}
