<?php

declare(strict_types=1);

namespace Gacela\Console;

use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Console\Domain\CommandArguments\CommandArguments;
use Gacela\Console\Domain\DependencyAnalyzer\TModuleDependency;
use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<ConsoleFactory>
 */
final class ConsoleFacade extends AbstractFacade
{
    public function sanitizeFilename(string $filename): string
    {
        return $this->getFactory()
            ->createFilenameSanitizer()
            ->sanitize($filename);
    }

    public function parseArguments(string $desiredNamespace): CommandArguments
    {
        return $this->getFactory()
            ->createCommandArgumentsParser()
            ->parse($desiredNamespace);
    }

    public function generateFileContent(
        CommandArguments $commandArguments,
        string $filename,
        bool $withShortName = false,
    ): string {
        return $this->getFactory()
            ->createFileContentGenerator()
            ->generate($commandArguments, $filename, $withShortName);
    }

    /**
     * @return list<AppModule>
     */
    public function findAllAppModules(string $filter = ''): array
    {
        return $this->getFactory()
            ->createAllAppModulesFinder()
            ->findAllAppModules($filter);
    }

    /**
     * @return array{
     *     registered_services: int,
     *     frozen_services: int,
     *     factory_services: int,
     *     bindings: int,
     *     cached_dependencies: int,
     *     memory_usage: string
     * }
     */
    public function getContainerStats(): array
    {
        return $this->getFactory()->getContainerStats();
    }

    /**
     * @param class-string $className
     *
     * @return list<string>
     */
    public function getContainerDependencyTree(string $className): array
    {
        return $this->getFactory()->getContainerDependencyTree($className);
    }

    /**
     * @param list<AppModule> $modules
     *
     * @return list<TModuleDependency>
     */
    public function analyzeModuleDependencies(array $modules): array
    {
        return $this->getFactory()->createDependencyAnalyzer()->analyzeModules($modules);
    }

    /**
     * @param list<TModuleDependency> $dependencies
     *
     * @return list<array{from: string, to: string}>
     */
    public function detectCircularDependencies(array $dependencies): array
    {
        return $this->getFactory()->createDependencyAnalyzer()->detectCircularDependencies($dependencies);
    }

    /**
     * @param list<TModuleDependency> $dependencies
     */
    public function formatDependencies(array $dependencies, string $format): string
    {
        return $this->getFactory()->createDependencyFormatter($format)->format($dependencies);
    }

    public function compileContainer(): string
    {
        return $this->getFactory()->createContainerCompiler()->compile(
            $this->getFactory()->getMainContainer(),
        );
    }
}
