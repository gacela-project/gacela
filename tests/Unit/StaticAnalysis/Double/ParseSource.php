<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Double;

use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
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
        return self::classInStatements(self::parse($php));
    }

    /**
     * Names rewritten in place, so `Name::toString()` is already qualified --
     * the tree PHPStan hands a rule.
     */
    public static function classInAsPhpStanResolves(string $php): ClassLike
    {
        return self::classInStatements(self::resolveNames(self::parse($php), replaceNodes: true));
    }

    /**
     * Names left as the source wrote them, with the qualified form on a
     * `resolvedName` attribute -- the shape Psalm hands a plugin.
     *
     * The difference is not cosmetic: reading `toString()` on this tree gives
     * `InvoiceRepository` for an imported class, which belongs to no module.
     */
    public static function classInWithNameAttributes(string $php): ClassLike
    {
        return self::classInStatements(self::resolveNames(self::parse($php), replaceNodes: false));
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
     * @param list<Stmt> $statements
     *
     * @return list<Stmt>
     */
    private static function resolveNames(array $statements, bool $replaceNodes): array
    {
        $traverser = new NodeTraverser(new NameResolver(null, ['replaceNodes' => $replaceNodes]));

        /** @var list<Stmt> $resolved */
        $resolved = $traverser->traverse($statements);

        return $resolved;
    }

    /**
     * @param list<Stmt> $statements
     */
    private static function classInStatements(array $statements): ClassLike
    {
        $node = (new NodeFinder())->findFirstInstanceOf($statements, ClassLike::class);

        if (!$node instanceof ClassLike) {
            throw new RuntimeException('The snippet declares no class');
        }

        return $node;
    }

    /**
     * @return list<Stmt>
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
