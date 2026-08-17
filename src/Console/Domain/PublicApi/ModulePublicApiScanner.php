<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\PublicApi;

use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\StaticAnalysis\ModuleBoundary;
use Gacela\StaticAnalysis\PublicApiSurface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;

use function dirname;
use function is_string;
use function sort;
use function str_replace;
use function strlen;
use function strrpos;
use function substr;

/**
 * What a module exports, listed rather than looked for.
 *
 * The surface is spread across a `#[PublicApi]` here and a `Shared\` namespace
 * there, which is the right place to *declare* it and the wrong place to read it
 * from: answering "what does Billing publish" meant opening every file in it.
 *
 * The same {@see ModuleBoundary} the analysers use decides, so what this prints
 * and what PHPStan and Psalm let through cannot be two different answers.
 */
final class ModulePublicApiScanner
{
    /**
     * @param list<string> $publicApiSegments sub-namespace names a module publishes
     */
    public function __construct(
        private readonly array $publicApiSegments = PublicApiSurface::DEFAULT_SEGMENTS,
    ) {
    }

    /**
     * Every class the module publishes, sorted -- a section that reordered
     * itself between runs would be unreadable in a diff.
     *
     * @return list<string>
     */
    public function scan(AppModule $module): array
    {
        $moduleNamespace = $module->fullModuleName();
        $rootNamespace = $this->parentNamespaceOf($moduleNamespace);
        $directory = $this->directoryOf($module);

        // A module sitting directly in the root namespace has no segment above
        // it to be a module *of*, which is the one case the boundary cannot
        // describe. It also cannot happen for a resolved facade.
        if ($rootNamespace === null || $directory === null) {
            return [];
        }

        $boundary = new ModuleBoundary($rootNamespace, 1, [], $this->publicApiSegments);

        $published = [];
        foreach ($this->classesIn($directory, $moduleNamespace) as $class) {
            if ($boundary->isPublicApi($class)) {
                $published[] = $class;
            }
        }

        sort($published);

        return $published;
    }

    /**
     * The class name each php file under the module would have, psr-4 style.
     *
     * Names rather than loaded classes: a file that holds no class, or holds one
     * under a different name, simply fails to be published -- the boundary
     * answers false for anything it cannot load, and a command that describes a
     * module must not be the thing that crashes on a stray script in it.
     *
     * @return iterable<string>
     */
    private function classesIn(string $directory, string $namespace): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($fileInfo->getPathname(), strlen($directory) + 1, -strlen('.php'));

            yield $namespace . '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        }
    }

    private function directoryOf(AppModule $module): ?string
    {
        try {
            $facadeFile = (new ReflectionClass($module->facadeClass()))->getFileName();
        } catch (Throwable) {
            return null;
        }

        return is_string($facadeFile) ? dirname($facadeFile) : null;
    }

    private function parentNamespaceOf(string $namespace): ?string
    {
        $position = strrpos($namespace, '\\');

        return $position === false ? null : substr($namespace, 0, $position);
    }
}
