<?php

declare(strict_types=1);

namespace Gacela\Console;

use AppendIterator;
use FilesystemIterator;
use Gacela\Console\Domain\AllAppModules\AllAppModulesFinder;
use Gacela\Console\Domain\AllAppModules\AppModuleCreator;
use Gacela\Console\Domain\CommandArguments\CommandArgumentsParser;
use Gacela\Console\Domain\CommandArguments\CommandArgumentsParserInterface;
use Gacela\Console\Domain\FileContent\FileContentGenerator;
use Gacela\Console\Domain\FileContent\FileContentGeneratorInterface;
use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizerInterface;
use Gacela\Console\Infrastructure\FileContentIo;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use OuterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;

use function is_dir;
use function sprintf;
use function str_starts_with;
use function strlen;
use function trigger_error;

/**
 * @extends AbstractFactory<ConsoleConfig>
 */
final class ConsoleFactory extends AbstractFactory
{
    /**
     * @return list<Command>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public function getConsoleCommands(): array
    {
        return (array)$this->getProvidedDependency(ConsoleProvider::COMMANDS);
    }

    public function createCommandArgumentsParser(): CommandArgumentsParserInterface
    {
        return new CommandArgumentsParser(
            $this->getConfig()->getComposerJsonContentAsArray(),
        );
    }

    public function createFilenameSanitizer(): FilenameSanitizerInterface
    {
        return new FilenameSanitizer();
    }

    public function createFileContentGenerator(): FileContentGeneratorInterface
    {
        return new FileContentGenerator(
            $this->createFileContentIo(),
            $this->getTemplateByFilenameMap(),
        );
    }

    public function createAllAppModulesFinder(): AllAppModulesFinder
    {
        return new AllAppModulesFinder(
            $this->createModuleScanIterator(),
            $this->createAppModuleCreator(),
        );
    }

    public function createAppModuleCreator(): AppModuleCreator
    {
        return new AppModuleCreator(
            new FactoryResolver(),
            new ConfigResolver(),
            new ProviderResolver(),
        );
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
        return $this->getMainContainer()->getStats();
    }

    /**
     * @param class-string $className
     *
     * @return list<string>
     */
    public function getContainerDependencyTree(string $className): array
    {
        return $this->getMainContainer()->getDependencyTree($className);
    }

    /**
     * @psalm-suppress MixedReturnTypeCoercion
     *
     * @return OuterIterator<array-key, SplFileInfo>
     */
    private function createModuleScanIterator(): OuterIterator
    {
        $paths = Config::getInstance()->getSetupGacela()->getAppModulePaths();
        $rootDir = Gacela::rootDir();

        if ($paths === []) {
            return $this->createRecursiveIteratorFor($rootDir);
        }

        $append = new AppendIterator();
        foreach ($paths as $path) {
            $resolved = $this->resolveScanPath($path, $rootDir);
            if (!is_dir($resolved)) {
                trigger_error(
                    sprintf('Gacela: appModulePaths entry "%s" is not a directory, skipping.', $path),
                    E_USER_WARNING,
                );
                continue;
            }

            $append->append($this->createRecursiveIteratorFor($resolved));
        }

        /** @var OuterIterator<array-key, SplFileInfo> $append */
        return $append;
    }

    /**
     * @return RecursiveIteratorIterator<RecursiveDirectoryIterator>
     */
    private function createRecursiveIteratorFor(string $dir): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
    }

    private function resolveScanPath(string $path, string $rootDir): string
    {
        if ($path === '') {
            return $rootDir;
        }

        if (str_starts_with($path, '/') || (strlen($path) > 1 && $path[1] === ':')) {
            return $path;
        }

        return rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    private function createFileContentIo(): FileContentIoInterface
    {
        return new FileContentIo();
    }

    /**
     * @psalm-suppress MixedReturnTypeCoercion
     *
     * @return array<string,string>
     */
    private function getTemplateByFilenameMap(): array
    {
        return (array)$this->getProvidedDependency(ConsoleProvider::TEMPLATE_BY_FILENAME_MAP);
    }

    private function getMainContainer(): Container
    {
        return Gacela::container();
    }
}
