<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ServiceMapMigration;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\StaticAnalysis\Rules\ServiceMapMissingAnalyser;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use Throwable;

use function count;
use function explode;
use function implode;
use function sprintf;

/**
 * Writes the `#[ServiceMap]` attribute that {@see ServiceMapMissingAnalyser}
 * reports as missing.
 *
 * The migration ladder for the 3.0 removal was deprecate, detect, suggest --
 * and then stop. The suggestion is literally the line to paste, so every user
 * was hand-applying an edit the tooling had already computed, one class at a
 * time, finding the classes only as cold resolves happened to reach them.
 *
 * Which accessors need an attribute is asked of the analyser rather than
 * decided again here. A codemod that migrated a different set than the analysis
 * reported would leave a build failing on the classes it just rewrote.
 *
 * The edit is textual and line-based, never a pretty-print of the parsed tree:
 * this rewrites code somebody else wrote, and reformatting a file to add one
 * line is not a migration anyone would run twice. The type is written exactly
 * as the docblock spells it, so `Foo::class` resolves through that file's own
 * imports -- group imports, aliases and all -- without this having to
 * understand them.
 */
final class ServiceMapMigrator
{
    private const IMPORT = 'use ' . ServiceMap::class . ';';

    public function __construct(
        private readonly Parser $parser,
        private readonly ServiceMapMissingAnalyser $analyser,
        private readonly NodeFinder $nodeFinder = new NodeFinder(),
    ) {
    }

    public function migrate(string $path, string $phpCode): MigrationResult
    {
        try {
            $ast = $this->parser->parse($phpCode);
        } catch (Throwable) {
            // A file this cannot parse is a file it must not rewrite. The run
            // reports it as untouched rather than failing: one unparsable file
            // in a tree is not a reason to abandon the rest.
            return MigrationResult::unchanged($path, $phpCode);
        }

        if ($ast === null) {
            return MigrationResult::unchanged($path, $phpCode);
        }

        // The analyser asks whether a trait is `ServiceResolverAwareTrait` by
        // its resolved name. Parsed without a name resolver, an imported trait
        // is only its short name and no class ever matches -- the migration
        // would report every file as clean. Line numbers survive the rewrite,
        // which is what the edit is keyed on.
        $ast = (new NodeTraverser(new NameResolver()))->traverse($ast);

        /** @var list<ClassLike> $classes */
        $classes = $this->nodeFinder->findInstanceOf($ast, ClassLike::class);

        $insertions = [];
        $declared = [];

        foreach ($classes as $class) {
            $accessors = $this->analyser->missingAccessors($class);
            if ($accessors === []) {
                continue;
            }

            $line = $class->getStartLine();

            foreach ($accessors as $method => $type) {
                $insertions[$line][] = sprintf(
                    "#[ServiceMap(method: '%s', className: %s::class)]",
                    $method,
                    $type,
                );

                $declared[] = sprintf('%s::%s()', (string)$class->name, $method);
            }
        }

        if ($insertions === []) {
            return MigrationResult::unchanged($path, $phpCode);
        }

        $lines = explode("\n", $phpCode);
        $importLine = $this->alreadyImported($ast) ? null : $this->importLine($ast);

        return new MigrationResult(
            $path,
            $phpCode,
            $this->rebuild($lines, $insertions, $importLine),
            $declared,
        );
    }

    /**
     * @param list<string> $lines
     * @param array<int, list<string>> $insertions one-based line => lines to put above it
     */
    private function rebuild(array $lines, array $insertions, ?int $importLine): string
    {
        $rebuilt = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            foreach ($insertions[$lineNumber] ?? [] as $attribute) {
                $rebuilt[] = $attribute;
            }

            $rebuilt[] = $line;

            if ($lineNumber === $importLine) {
                $rebuilt[] = self::IMPORT;
            }
        }

        return implode("\n", $rebuilt);
    }

    /**
     * The line to put the import after: the last `use` of the file, or the
     * `namespace` when it imports nothing yet.
     *
     * Placed after rather than sorted in, because sorting is the formatter's
     * job and this repository runs one. Guessing at alphabetical order would
     * only be right until a file disagreed with the guess.
     *
     * @param array<array-key, \PhpParser\Node> $ast
     */
    private function importLine(array $ast): ?int
    {
        /** @var list<Use_> $uses */
        $uses = $this->nodeFinder->findInstanceOf($ast, Use_::class);
        if ($uses !== []) {
            $last = $uses[count($uses) - 1];

            return $last->getEndLine();
        }

        /** @var list<Namespace_> $namespaces */
        $namespaces = $this->nodeFinder->findInstanceOf($ast, Namespace_::class);
        if ($namespaces !== []) {
            return $namespaces[0]->getStartLine();
        }

        return null;
    }

    /**
     * @param array<array-key, \PhpParser\Node> $ast
     */
    private function alreadyImported(array $ast): bool
    {
        /** @var list<Use_> $uses */
        $uses = $this->nodeFinder->findInstanceOf($ast, Use_::class);

        foreach ($uses as $use) {
            foreach ($use->uses as $useUse) {
                if ($useUse->name->toString() === ServiceMap::class) {
                    return true;
                }
            }
        }

        return false;
    }
}
