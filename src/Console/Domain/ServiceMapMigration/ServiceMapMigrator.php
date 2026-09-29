<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ServiceMapMigration;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\StaticAnalysis\Rules\ServiceMapMissingAnalyser;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use Throwable;

use function array_filter;
use function array_values;
use function count;
use function explode;
use function implode;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strcasecmp;
use function substr;
use function trim;

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

    private const IMPORT_SORT_KEY = 'Gacela Framework ServiceResolver ServiceMap';

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
        $imports = $this->importStatements($ast);
        $importInsertion = $this->alreadyImported($imports) ? [] : $this->importInsertion($ast, $imports, $phpCode);

        return new MigrationResult(
            $path,
            $phpCode,
            $this->rebuild($lines, $insertions, $importInsertion),
            $declared,
        );
    }

    /**
     * @param list<string> $lines
     * @param array<int, list<string>> $insertions one-based line => lines to put above it
     * @param array<int, list<string>> $importInsertion one-based line => lines to put above it
     */
    private function rebuild(array $lines, array $insertions, array $importInsertion): string
    {
        $rebuilt = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            foreach ($importInsertion[$lineNumber] ?? [] as $import) {
                $rebuilt[] = $import;
            }

            foreach ($insertions[$lineNumber] ?? [] as $attribute) {
                $rebuilt[] = $attribute;
            }

            $rebuilt[] = $line;
        }

        return implode("\n", $rebuilt);
    }

    /**
     * The import-block statements of the first namespace, or of the file when
     * it declares none.
     *
     * @param array<array-key, Node> $ast
     *
     * @return list<Use_|GroupUse>
     */
    private function importStatements(array $ast): array
    {
        $namespace = $this->nodeFinder->findFirstInstanceOf($ast, Namespace_::class);
        $statements = $namespace instanceof Namespace_ ? $namespace->stmts : $ast;

        $imports = [];
        foreach ($statements as $statement) {
            if ($statement instanceof Use_ || $statement instanceof GroupUse) {
                $imports[] = $statement;
            }
        }

        return $imports;
    }

    /**
     * Where the import goes so an `ordered_imports` formatter has nothing left
     * to move: among the class imports in alphabetical order, and above any
     * function or const import, since classes come first.
     *
     * @param array<array-key, Node> $ast
     * @param list<Use_|GroupUse> $imports
     *
     * @return array<int, list<string>> one-based line => lines to put above it
     */
    private function importInsertion(array $ast, array $imports, string $phpCode): array
    {
        $classImports = array_values(array_filter($imports, $this->importsClasses(...)));

        foreach ($classImports as $import) {
            if (strcasecmp($this->sortKey($import, $phpCode), self::IMPORT_SORT_KEY) > 0) {
                return [$this->firstLine($import) => [self::IMPORT]];
            }
        }

        if ($classImports !== []) {
            return [$classImports[count($classImports) - 1]->getEndLine() + 1 => [self::IMPORT]];
        }

        if ($imports !== []) {
            return [$this->firstLine($imports[0]) => [self::IMPORT, '']];
        }

        $namespace = $this->nodeFinder->findFirstInstanceOf($ast, Namespace_::class);
        if ($namespace instanceof Namespace_) {
            return [$namespace->getStartLine() + 1 => ['', self::IMPORT]];
        }

        return [];
    }

    private function importsClasses(Use_|GroupUse $import): bool
    {
        if ($import instanceof Use_) {
            return $import->type === Use_::TYPE_NORMAL;
        }

        foreach ($import->uses as $item) {
            if ($item->type !== Use_::TYPE_NORMAL) {
                return false;
            }
        }

        return true;
    }

    /**
     * The key php-cs-fixer's `ordered_imports` sorts by: the imported text,
     * alias and group braces included, with separators read as spaces.
     */
    private function sortKey(Use_|GroupUse $import, string $phpCode): string
    {
        $text = substr(
            $phpCode,
            $import->getStartFilePos(),
            $import->getEndFilePos() - $import->getStartFilePos() + 1,
        );
        $text = (string)preg_replace(['/^use\s+/i', '/\s*;$/', '%/\*.*?\*/%s'], '', $text);

        return str_replace(['\\', '{'], [' ', ''], trim($text));
    }

    /**
     * A comment above an import belongs to it, so the insert goes above both.
     */
    private function firstLine(Use_|GroupUse $import): int
    {
        $comments = $import->getComments();

        return $comments === [] ? $import->getStartLine() : $comments[0]->getStartLine();
    }

    /**
     * Imported under another alias, `#[ServiceMap]` would still not resolve,
     * so only an import that binds the short name counts.
     *
     * @param list<Use_|GroupUse> $imports
     */
    private function alreadyImported(array $imports): bool
    {
        foreach ($imports as $import) {
            $prefix = $import instanceof GroupUse ? $import->prefix->toString() . '\\' : '';

            foreach ($import->uses as $item) {
                if ($prefix . $item->name->toString() === ServiceMap::class
                    && $item->getAlias()->toString() === 'ServiceMap'
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
