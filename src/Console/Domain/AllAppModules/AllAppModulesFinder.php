<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Gacela\Framework\AbstractFacade;
use OuterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function sprintf;

final class AllAppModulesFinder
{
    /**
     * @param RecursiveIteratorIterator<RecursiveDirectoryIterator> $fileIterator
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
            $appModule = $this->createAppModule($fileInfo, $filter);
            if ($appModule instanceof AppModule && $this->isFacade($appModule)) {
                $result[$appModule->facadeClass()] = $appModule;
            }
        }

        uksort($result, static fn ($a, $b): int => $a <=> $b);

        return array_values($result);
    }

    private function createAppModule(SplFileInfo $fileInfo, string $filter): ?AppModule
    {
        if (!$fileInfo->isFile()
            || $fileInfo->getExtension() !== 'php'
            || str_starts_with($fileInfo->getFilename(), '.')
            || str_contains($fileInfo->getRealPath(), 'vendor' . DIRECTORY_SEPARATOR)
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

        return $this->appModuleCreator->fromClass($fullyQualifiedClassName);
    }

    private function getNamespace(SplFileInfo $fileInfo): string
    {
        $fileContent = file_get_contents($fileInfo->getRealPath());
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

    private function isFacade(AppModule $appModule): bool
    {
        $rc = new ReflectionClass($appModule->facadeClass());
        $parentClass = $rc->getParentClass();

        return $parentClass !== false
            && $parentClass->name === AbstractFacade::class;
    }
}
