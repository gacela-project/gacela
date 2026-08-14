<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Gacela\Framework\AbstractFacade;
use OuterIterator;
use ReflectionClass;
use SplFileInfo;

use function sprintf;

final class AllAppModulesFinder
{
    /**
     * @param OuterIterator<array-key, SplFileInfo> $fileIterator
     */
    public function __construct(
        private readonly OuterIterator $fileIterator,
        private readonly AppModuleCreator $appModuleCreator,
    ) {
    }

    /**
     * @return list<AppModule>
     */
    public function findAllAppModules(string $filter): array
    {
        $result = [];

        /** @var SplFileInfo $fileInfo */
        foreach ($this->fileIterator as $fileInfo) {
            $facadeClass = $this->findFacadeClass($fileInfo, $filter);
            if ($facadeClass === null) {
                continue;
            }

            $result[$facadeClass] = $this->appModuleCreator->fromClass($facadeClass);
        }

        uksort($result, static fn ($a, $b): int => $a <=> $b);

        return array_values($result);
    }

    /**
     * The class this file declares, when it is a Facade -- and nothing more.
     *
     * The module used to be built first and the Facade question asked of it
     * afterwards, so every class in the project got an `AppModule`: three class
     * resolvers run for a Factory, a Config and a Provider, each one resolving
     * to an *instance*, and the whole thing discarded for the ~90% of classes
     * that are not Facades at all. Asking first costs a `ReflectionClass` and
     * answers for the same set.
     *
     * @return ?class-string
     */
    private function findFacadeClass(SplFileInfo $fileInfo, string $filter): ?string
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

        $namespace = $this->getNamespace($fileInfo);
        $className = $this->buildClassName($fileInfo);

        if ($className === '' || $namespace === '') {
            return null;
        }

        $fullyQualifiedClassName = sprintf(
            '%s\\%s',
            $namespace,
            $className,
        );

        if ($filter !== '') {
            $filterNamespace = str_replace('/', '\\', $filter);
            if (!str_contains($fullyQualifiedClassName, $filterNamespace)) {
                return null;
            }
        }

        if (!class_exists($fullyQualifiedClassName)) {
            return null;
        }

        if (!$this->isFacade($fullyQualifiedClassName)) {
            return null;
        }

        return $fullyQualifiedClassName;
    }

    private function getNamespace(SplFileInfo $fileInfo): string
    {
        $realPath = $fileInfo->getRealPath();
        if ($realPath === false) {
            return '';
        }

        $fileContent = file_get_contents($realPath);
        if ($fileContent === false) {
            return '';
        }

        preg_match('#namespace (.*);#', $fileContent, $matches);

        return $matches[1] ?? '';
    }

    private function buildClassName(SplFileInfo $fileInfo): string
    {
        $pieces = explode(DIRECTORY_SEPARATOR, $fileInfo->getFilename());
        $filename = end($pieces);

        $dotPos = strpos($filename, '.');

        return $dotPos !== false ? substr($filename, 0, $dotPos) : $filename;
    }

    /**
     * Any descendant counts, not only a direct child. A project that puts its
     * own base facade in between -- `RealFacade extends ProjectBaseFacade
     * extends AbstractFacade` -- is a normal shape, and comparing the immediate
     * parent by name dropped every one of those modules from list, doctor,
     * graph and cache-warm without saying so.
     *
     * isSubclassOf() is false for AbstractFacade itself, which is what we want:
     * the base class is not a module.
     *
     * @param class-string $className
     */
    private function isFacade(string $className): bool
    {
        return (new ReflectionClass($className))
            ->isSubclassOf(AbstractFacade::class);
    }
}
