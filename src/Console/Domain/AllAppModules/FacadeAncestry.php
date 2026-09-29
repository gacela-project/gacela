<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Closure;
use Composer\Autoload\ClassLoader;
use Gacela\Framework\AbstractFacade;

use function array_key_exists;
use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Proves from source, without loading anything, that a class cannot descend
 * from `AbstractFacade`: its `extends` chain is read file by file, each file
 * found through Composer's `findFile()`, until it ends at a class with no
 * parent.
 *
 * Only the "no" answer is trusted. Anything the parse cannot be sure of (a file
 * Composer cannot locate, a body or a group `use` before the declaration, two
 * declarations of the name, a cycle) is "unknown", and the caller loads the
 * class as before. A wrong "no" loses a module silently; a wrong "unknown"
 * costs one load.
 */
final class FacadeAncestry
{
    private const MAX_DEPTH = 32;

    /** @var array<string, ?bool> */
    private array $verdicts = [];

    /**
     * @param Closure(string): ?string $classFileLocator
     */
    public function __construct(
        private readonly Closure $classFileLocator,
    ) {
    }

    public static function fromRegisteredAutoloaders(): self
    {
        $loaders = [];
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof ClassLoader) {
                $loaders[] = $autoloader[0];
            }
        }

        return new self(static function (string $className) use ($loaders): ?string {
            foreach ($loaders as $loader) {
                $file = $loader->findFile($className);
                if (is_string($file)) {
                    return $file;
                }
            }

            return null;
        });
    }

    public function rulesOut(string $className): bool
    {
        return $this->descendsFromAbstractFacade($className, 0) === false;
    }

    private function descendsFromAbstractFacade(string $className, int $depth): ?bool
    {
        $key = strtolower($className);
        if ($key === strtolower(AbstractFacade::class)) {
            return true;
        }

        if (array_key_exists($key, $this->verdicts)) {
            return $this->verdicts[$key];
        }

        if ($depth >= self::MAX_DEPTH) {
            return null;
        }

        if (class_exists($className, false)) {
            return $this->verdicts[$key] = is_subclass_of($className, AbstractFacade::class);
        }

        $parent = $this->declaredParent($className);
        if ($parent === null) {
            return $this->verdicts[$key] = null;
        }

        if ($parent === '') {
            return $this->verdicts[$key] = false;
        }

        return $this->verdicts[$key] = $this->descendsFromAbstractFacade($parent, $depth + 1);
    }

    /**
     * @return ?string the parent's fully qualified name, '' when it has none, null when unknown
     */
    private function declaredParent(string $className): ?string
    {
        $file = ($this->classFileLocator)($className);
        if ($file === null || !is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $separator = strrpos($className, '\\');
        $shortName = preg_quote($separator === false ? $className : substr($className, $separator + 1), '/');

        $mentions = preg_match_all(sprintf('/\bclass\s+%s\b/i', $shortName), $content);
        $declarations = preg_match_all(
            sprintf('/\bclass\s+%s\s*(?:\bextends\s+(\\\\?[\w\\\\]+)\s*)?(?:\bimplements\b[^{]*)?\{/i', $shortName),
            $content,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        if ($mentions !== 1 || $declarations !== 1) {
            return null;
        }

        $parentName = $matches[1][0][0] ?? '';
        if ($parentName === '') {
            return '';
        }

        $header = substr($content, 0, $matches[0][0][1]);
        if (str_contains($header, '{')) {
            return null;
        }

        return $this->resolveName($parentName, $header);
    }

    private function resolveName(string $name, string $header): ?string
    {
        if (str_starts_with($name, '\\')) {
            return substr($name, 1);
        }

        if (preg_match_all('/\bnamespace\s+([\w\\\\]+)\s*;/i', $header, $namespaces) > 1) {
            return null;
        }

        $namespace = $namespaces[1][0] ?? '';
        if (strtolower($name) === 'namespace' || str_starts_with(strtolower($name), 'namespace\\')) {
            return null;
        }

        $imports = $this->imports($header);
        if ($imports === null) {
            return null;
        }

        $segments = explode('\\', $name, 2);
        $alias = strtolower($segments[0]);
        if (isset($imports[$alias])) {
            return $imports[$alias] . (count($segments) === 2 ? '\\' . $segments[1] : '');
        }

        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * @return ?array<string, string> lowercased alias to imported name
     */
    private function imports(string $header): ?array
    {
        preg_match_all('/^\s*use\s+([^;]+);/mi', $header, $statements);

        $imports = [];
        foreach ($statements[1] as $statement) {
            if (preg_match('/^(?:function|const)\s/i', $statement) === 1) {
                continue;
            }

            foreach (explode(',', $statement) as $clause) {
                if (preg_match('/^\s*\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*$/i', $clause, $parts) !== 1) {
                    return null;
                }

                $imported = $parts[1];
                $separator = strrpos($imported, '\\');
                $alias = $parts[2] ?? '';
                if ($alias === '') {
                    $alias = $separator === false ? $imported : substr($imported, $separator + 1);
                }

                $imports[strtolower($alias)] = $imported;
            }
        }

        return $imports;
    }
}
