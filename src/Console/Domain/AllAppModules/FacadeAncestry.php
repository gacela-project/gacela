<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Composer\Autoload\ClassLoader;
use Gacela\Framework\AbstractFacade;

use function array_key_exists;
use function count;
use function sprintf;

/**
 * Proves from source, without loading anything, that a class cannot descend
 * from `AbstractFacade`: its `extends` chain is read file by file, each file
 * found through Composer's `findFile()`, until it ends at a class with no
 * parent.
 *
 * Only the "no" answer is trusted. Anything the parse cannot be sure of (a file
 * Composer cannot locate, a `{` before the declaration, a second mention of the
 * name, a cycle) is "unknown", and the caller loads the class as before. A
 * wrong "no" loses a module silently; a wrong "unknown" costs one load.
 */
final class FacadeAncestry
{
    /** @var array<string, ?bool> */
    private array $verdicts = [];

    /**
     * @param array<ClassLoader> $classLoaders
     */
    public function __construct(
        private readonly array $classLoaders = [],
    ) {
    }

    public static function fromRegisteredAutoloaders(): self
    {
        return new self(ClassLoader::getRegisteredLoaders());
    }

    public function rulesOut(string $className): bool
    {
        return $this->descendsFromAbstractFacade($className) === false;
    }

    private function descendsFromAbstractFacade(string $className): ?bool
    {
        if (strcasecmp($className, AbstractFacade::class) === 0) {
            return true;
        }

        if (array_key_exists($className, $this->verdicts)) {
            return $this->verdicts[$className];
        }

        if (class_exists($className, false)) {
            return $this->verdicts[$className] = is_subclass_of($className, AbstractFacade::class);
        }

        // Marked unknown while its parents are walked, so a cycle ends here.
        $this->verdicts[$className] = null;

        $parent = $this->declaredParent($className);
        if ($parent === null) {
            return null;
        }

        if ($parent === '') {
            return $this->verdicts[$className] = false;
        }

        return $this->verdicts[$className] = $this->descendsFromAbstractFacade($parent);
    }

    /**
     * @return ?string the parent's fully qualified name, '' when it has none, null when unknown
     */
    private function declaredParent(string $className): ?string
    {
        $file = $this->findFile($className);
        if ($file === null || !is_file($file)) {
            return null;
        }

        $content = (string) file_get_contents($file);
        $separator = strrpos($className, '\\');
        $shortName = $separator === false ? $className : substr($className, $separator + 1);

        $mentions = preg_match_all(sprintf('/\bclass\s+%s\b/i', $shortName), $content);
        $declared = preg_match(
            sprintf('/\A[^{]*\bclass\s+%s\s*(?:\bextends\s+(\\\\?[\w\\\\]+)\s*)?(?:\bimplements\b[^{]*)?\{/i', $shortName),
            $content,
            $declaration,
        );

        if ($mentions !== 1 || $declared !== 1) {
            return null;
        }

        $parentName = $declaration[1] ?? '';
        if ($parentName === '') {
            return '';
        }

        return $this->resolveName($parentName, $declaration[0]);
    }

    private function findFile(string $className): ?string
    {
        foreach ($this->classLoaders as $classLoader) {
            $file = $classLoader->findFile($className);
            if ($file !== false) {
                return $file;
            }
        }

        return null;
    }

    private function resolveName(string $name, string $header): ?string
    {
        if (str_starts_with($name, '\\')) {
            return substr($name, 1);
        }

        if (preg_match_all('/\bnamespace\s+([\w\\\\]+)\s*;/', $header, $namespaces) > 1) {
            return null;
        }

        $imports = $this->imports($header);
        $segments = explode('\\', $name, 2);
        $imported = $imports[strtolower($segments[0])] ?? null;
        if ($imported !== null) {
            return $imported . (count($segments) === 2 ? '\\' . $segments[1] : '');
        }

        $namespace = $namespaces[1][0] ?? '';

        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * @return array<string, string> lowercased alias to imported name
     */
    private function imports(string $header): array
    {
        preg_match_all('/^\s*use\s+\K(?!function\s|const\s)[^;]+(?=;)/m', $header, $statements);

        $imports = [];
        foreach ($statements[0] as $statement) {
            preg_match_all('/\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?/', $statement, $clauses, PREG_SET_ORDER);
            foreach ($clauses as $clause) {
                $imported = $clause[1];
                $alias = $clause[2] ?? basename(str_replace('\\', '/', $imported));
                $imports[strtolower($alias)] = $imported;
            }
        }

        return $imports;
    }
}
