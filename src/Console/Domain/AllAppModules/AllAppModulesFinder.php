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
    public function findAllAppModules(): array
    {
        $result = [];

        /** @var SplFileInfo $fileInfo */
        foreach ($this->fileIterator as $fileInfo) {
            $appModule = $this->createAppModule($fileInfo);
            if ($appModule !== null && $this->isFacade($appModule)) {
                $result[] = $appModule;
            }
        }

        return $result;
    }

    private function isFacade(AppModule $appModule): bool
    {
        $rc = new ReflectionClass($appModule->fullyQualifiedClassName());
        $parentClass = $rc->getParentClass();

        return $parentClass
            && $parentClass->name === AbstractFacade::class;
    }

    private function buildClassName(SplFileInfo $fileInfo): string
    {
        $pieces = explode(DIRECTORY_SEPARATOR, $fileInfo->getFilename());
        $filename = end($pieces);

        return substr($filename, 0, strpos($filename, '.') ?: 1);
    }

    private function getNamespace(SplFileInfo $fileInfo): string
    {
        $fileContent = (string)file_get_contents($fileInfo->getRealPath());

        preg_match('#namespace (.*);#', $fileContent, $matches);

        return $matches[1] ?? '';
    }

    private function createAppModule(SplFileInfo $fileInfo): ?AppModule
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

        if (!class_exists($fullyQualifiedClassName)) {
            return null;
        }

        return new AppModule($className, $namespace);
    }
}
