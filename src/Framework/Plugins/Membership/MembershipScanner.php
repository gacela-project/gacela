<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use Gacela\Framework\Attribute\Plugin;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function class_exists;
use function file_get_contents;
use function in_array;
use function is_dir;
use function ltrim;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function usort;

use const DIRECTORY_SEPARATOR;

/**
 * Finds the `#[Plugin]` classes of the application by walking its module paths.
 *
 * A file is loaded only when its source mentions `Plugin(` and declares a class
 * inside `projectNamespaces`: a loose match costs one class load, never a wrong
 * member, because membership is read from the attribute itself.
 *
 * @internal
 */
final class MembershipScanner
{
    /** The console's module scan prunes the same directories. */
    private const EXCLUDED_DIRECTORIES = ['vendor', 'node_modules'];

    /**
     * @param list<string> $directories
     * @param list<string> $projectNamespaces
     */
    public function __construct(
        private readonly array $directories,
        private readonly array $projectNamespaces,
    ) {
    }

    /**
     * @param list<string> $appModulePaths empty means the whole application root
     * @param list<string> $projectNamespaces
     */
    public static function forPaths(array $appModulePaths, string $rootDir, array $projectNamespaces): self
    {
        $directories = [];
        foreach ($appModulePaths === [] ? [''] : $appModulePaths as $path) {
            $directories[] = self::resolve($path, $rootDir);
        }

        return new self($directories, $projectNamespaces);
    }

    /**
     * @return list<PluginMember>
     */
    public function plugins(): array
    {
        $members = [];

        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach ($this->phpFilesIn($directory) as $file) {
                $className = $this->candidateClassIn($file);
                if ($className === null) {
                    continue;
                }

                foreach ((new ReflectionClass($className))->getAttributes(Plugin::class) as $attribute) {
                    $plugin = $attribute->newInstance();
                    $members[] = new PluginMember($plugin->contract, $className, $plugin->priority);
                }
            }
        }

        usort($members, PluginMember::compare(...));

        return $members;
    }

    /**
     * @return class-string|null
     */
    private function candidateClassIn(SplFileInfo $file): ?string
    {
        $source = (string) file_get_contents($file->getPathname());

        if (!str_contains($source, '#[') || preg_match('/\bPlugin\s*\(/', $source) !== 1) {
            return null;
        }

        if (preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $source, $namespace) !== 1
            || preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m', $source, $class) !== 1
        ) {
            return null;
        }

        $className = $namespace[1] . '\\' . $class[1];

        if (!$this->isInsideProjectNamespaces($className) || !class_exists($className)) {
            return null;
        }

        return $className;
    }

    private function isInsideProjectNamespaces(string $className): bool
    {
        if ($this->projectNamespaces === []) {
            return true;
        }

        foreach ($this->projectNamespaces as $namespace) {
            if (str_starts_with($className, rtrim($namespace, '\\') . '\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFilesIn(string $directory): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            static fn (mixed $current, string $key, RecursiveDirectoryIterator $iterator): bool => $iterator->hasChildren()
                ? !str_starts_with($iterator->getFilename(), '.')
                    && !in_array($iterator->getFilename(), self::EXCLUDED_DIRECTORIES, true)
                : str_ends_with($iterator->getFilename(), '.php'),
        ));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            yield $file;
        }
    }

    private static function resolve(string $path, string $rootDir): string
    {
        if ($path === '') {
            return $rootDir;
        }

        if (str_starts_with($path, '/') || (strlen($path) > 1 && $path[1] === ':')) {
            return $path;
        }

        return rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
