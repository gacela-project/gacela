<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Gacela\Framework\AbstractFacade;
use OuterIterator;
use ReflectionClass;
use SplFileInfo;

final class AllAppModulesFinder
{
    public function __construct(
        private OuterIterator $fileIterator,
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
            $appModule = $this->createAppModule($fileInfo, $filter);
            if ($appModule !== null && $this->isFacade($appModule)) {
                $result[$appModule->facadeClass()] = $appModule;
            }
        }
        uksort($result, static fn ($a, $b) => $a <=> $b);

        return array_values($result);
    }

    private function createAppModule(SplFileInfo $fileInfo, string $filter): ?AppModule
    {
        if (!$fileInfo->isFile()
            || $fileInfo->getExtension() !== 'php'
            || str_contains($fileInfo->getRealPath(), 'vendor/')
        ) {
            return null;
        }

        $namespace = $this->getNamespace($fileInfo);
        $className = $this->buildClassName($fileInfo);

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

        return AppModule::fromClass($fullyQualifiedClassName);
    }

    private function getNamespace(SplFileInfo $fileInfo): string
    {
        $fileContent = (string)file_get_contents($fileInfo->getRealPath());

        preg_match('#namespace (.*);#', $fileContent, $matches);

        return $matches[1] ?? '';
    }

    private function buildClassName(SplFileInfo $fileInfo): string
    {
        $pieces = explode(DIRECTORY_SEPARATOR, $fileInfo->getFilename());
        $filename = end($pieces);

        return substr($filename, 0, strpos($filename, '.') ?: 1);
    }

    private function isFacade(AppModule $appModule): bool
    {
        $rc = new ReflectionClass($appModule->facadeClass());
        $parentClass = $rc->getParentClass();

        return $parentClass
            && $parentClass->name === AbstractFacade::class;
    }
}
