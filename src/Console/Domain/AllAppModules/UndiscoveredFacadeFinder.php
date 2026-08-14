<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Gacela\Framework\AbstractFacade;
use OuterIterator;
use ReflectionClass;
use SplFileInfo;

use function class_exists;
use function dirname;
use function enum_exists;
use function interface_exists;
use function trait_exists;

/**
 * Files named like a Facade that discovery did not turn into a module.
 *
 * Every other check starts from the modules discovery *found*, so a module that
 * was never found is invisible to all of them -- including the directory scan
 * in `FilenameMismatchCheck`, which walks the directory of each discovered
 * module. One broken Facade in fifty leaves forty-nine modules and no warning
 * anywhere; the empty-listing hint only appears when *nothing* was found.
 */
final class UndiscoveredFacadeFinder
{
    /**
     * @param OuterIterator<array-key, SplFileInfo> $fileIterator
     * @param list<string> $facadeSuffixes
     */
    public function __construct(
        private readonly OuterIterator $fileIterator,
        private readonly array $facadeSuffixes,
    ) {
    }

    /**
     * @return list<UndiscoveredFacadeFile>
     */
    public function find(): array
    {
        $found = [];

        /** @var SplFileInfo $fileInfo */
        foreach ($this->fileIterator as $fileInfo) {
            $undiscovered = $this->inspect($fileInfo);
            if ($undiscovered instanceof UndiscoveredFacadeFile) {
                $found[$undiscovered->className] = $undiscovered;
            }
        }

        uksort($found, static fn (string $a, string $b): int => $a <=> $b);

        return array_values($found);
    }

    private function inspect(SplFileInfo $fileInfo): ?UndiscoveredFacadeFile
    {
        $realPath = $fileInfo->getRealPath();

        if ($realPath === false
            || !$fileInfo->isFile()
            || $fileInfo->getExtension() !== 'php'
            || str_starts_with($fileInfo->getFilename(), '.')
            || str_contains($realPath, 'vendor' . DIRECTORY_SEPARATOR)
        ) {
            return null;
        }

        $className = $this->classNameOf($fileInfo);
        if ($className === '' || !$this->looksLikeModuleFacade($className, $realPath)) {
            return null;
        }

        $namespace = $this->namespaceOf($realPath);
        if ($namespace === '') {
            return null;
        }

        $fullyQualifiedClassName = $namespace . '\\' . $className;

        if (!class_exists($fullyQualifiedClassName)) {
            // An interface, trait or enum named like a Facade is loadable and
            // simply not a module. Reporting it would be reporting a decision.
            if (interface_exists($fullyQualifiedClassName)
                || trait_exists($fullyQualifiedClassName)
                || enum_exists($fullyQualifiedClassName)
            ) {
                return null;
            }

            return new UndiscoveredFacadeFile(
                $realPath,
                $fullyQualifiedClassName,
                UndiscoveredFacadeProblem::NotLoadable,
            );
        }

        if ((new ReflectionClass($fullyQualifiedClassName))->isSubclassOf(AbstractFacade::class)) {
            return null;
        }

        // AbstractFacade itself, and any project base facade sitting between it
        // and the real ones, are named like facades and are not modules.
        if ($fullyQualifiedClassName === AbstractFacade::class
            || (new ReflectionClass($fullyQualifiedClassName))->isAbstract()
        ) {
            return null;
        }

        return new UndiscoveredFacadeFile(
            $realPath,
            $fullyQualifiedClassName,
            UndiscoveredFacadeProblem::NotAFacade,
        );
    }

    /**
     * Named the way the scaffolder names a module's Facade, and only that:
     * `Blog/BlogFacade.php`, or `Blog/Facade.php` for a `--short-name` module.
     *
     * Ending in the suffix is not enough. `Facade` is an ordinary word -- this
     * finder's own `UndiscoveredFacadeFile` ends in it, and so will a project's
     * `NullFacade` or `FacadeRegistry` -- and reporting those would bury the one
     * case worth reading under the ones that were never modules. A class named
     * after its own directory is a module or a mistake, and both are worth
     * saying.
     */
    private function looksLikeModuleFacade(string $className, string $realPath): bool
    {
        $moduleName = basename(dirname($realPath));

        foreach ($this->facadeSuffixes as $suffix) {
            if ($suffix === '') {
                continue;
            }

            if ($className === $suffix || $className === $moduleName . $suffix) {
                return true;
            }
        }

        return false;
    }

    private function classNameOf(SplFileInfo $fileInfo): string
    {
        $filename = $fileInfo->getFilename();
        $dotPos = strpos($filename, '.');

        return $dotPos !== false ? substr($filename, 0, $dotPos) : $filename;
    }

    private function namespaceOf(string $realPath): string
    {
        $fileContent = file_get_contents($realPath);
        if ($fileContent === false) {
            return '';
        }

        preg_match('#namespace (.*);#', $fileContent, $matches);

        return $matches[1] ?? '';
    }
}
