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
use PhpParser\Node\UseItem;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use Throwable;

use function explode;
use function implode;
use function sprintf;
use function str_replace;
use function strcasecmp;
use function strrpos;
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

            // Edits go in as whole lines above the class, so code in front of
            // it on the same line -- a one-line file -- would end up below
            // them, and above `<?php`. Such a file is left as it is.
            if (!$this->startsItsLine($phpCode, $class->getStartFilePos())) {
                return MigrationResult::unchanged($path, $phpCode);
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
        $importInsertion = $this->alreadyImported($imports) ? [] : $this->importInsertion($ast, $imports);
        if ($importInsertion !== [] && !$this->importsStartTheirLines($phpCode, $ast, $imports)) {
            return MigrationResult::unchanged($path, $phpCode);
        }

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
    private function importInsertion(array $ast, array $imports): array
    {
        $lastClassImport = null;

        foreach ($imports as $import) {
            if (!$this->importsClasses($import)) {
                continue;
            }

            if (strcasecmp($this->sortKey($import), self::IMPORT_SORT_KEY) > 0) {
                return [$this->firstLine($import) => [self::IMPORT]];
            }

            $lastClassImport = $import;
        }

        if ($lastClassImport !== null) {
            return [$lastClassImport->getEndLine() + 1 => [self::IMPORT]];
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
     * The key php-cs-fixer's `ordered_imports` sorts by, the imported name and
     * its alias with separators read as spaces. The fixer's key runs on past
     * the first item of a group, which can never change how it compares with
     * the one import this adds.
     */
    private function sortKey(Use_|GroupUse $import): string
    {
        $item = $import->uses[0];
        $key = $this->importedName($import, $item);
        if ($item->alias !== null) {
            $key .= ' as ' . $item->alias->toString();
        }

        return str_replace('\\', ' ', $key);
    }

    private function importedName(Use_|GroupUse $import, UseItem $item): string
    {
        $prefix = $import instanceof GroupUse ? $import->prefix->toString() . '\\' : '';

        return $prefix . $item->name->toString();
    }

    /**
     * A comment above an import belongs to it, so the insert goes above both.
     */
    private function startsItsLine(string $code, int $position): bool
    {
        $lineStart = strrpos(substr($code, 0, $position), "\n");
        $prefix = substr($code, $lineStart === false ? 0 : $lineStart + 1, $position - ($lineStart === false ? 0 : $lineStart + 1));

        return trim($prefix) === '';
    }

    /**
     * The import goes in as a whole line beside the existing imports, or below
     * the namespace line: each must stand on a line of its own.
     *
     * @param array<array-key, Node> $ast
     * @param list<Use_|GroupUse> $imports
     */
    private function importsStartTheirLines(string $code, array $ast, array $imports): bool
    {
        foreach ($imports as $import) {
            if (!$this->startsItsLine($code, $import->getStartFilePos())) {
                return false;
            }
        }

        $namespace = $this->nodeFinder->findFirstInstanceOf($ast, Namespace_::class);
        if (!$namespace instanceof Namespace_) {
            return true;
        }

        $first = $namespace->stmts[0] ?? null;

        return $this->startsItsLine($code, $namespace->getStartFilePos())
            && (!$first instanceof Node || $first->getStartLine() > $namespace->getStartLine());
    }

    private function firstLine(Use_|GroupUse $import): int
    {
        $comments = $import->getComments();

        return $comments === [] ? $import->getStartLine() : $comments[0]->getStartLine();
    }

    /**
     * Imported under another alias, `#[ServiceMap]` would still not resolve,
     * so only an import that binds the short name counts. Class names are
     * case-insensitive, and a second import of the same name is a fatal error.
     *
     * @param list<Use_|GroupUse> $imports
     */
    private function alreadyImported(array $imports): bool
    {
        foreach ($imports as $import) {
            foreach ($import->uses as $item) {
                if (strcasecmp($this->importedName($import, $item), ServiceMap::class) === 0
                    && strcasecmp($item->getAlias()->toString(), 'ServiceMap') === 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
