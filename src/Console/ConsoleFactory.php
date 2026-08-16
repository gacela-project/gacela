<?php

declare(strict_types=1);

namespace Gacela\Console;

use AppendIterator;
use FilesystemIterator;
use Gacela\Console\Application\DtoGenerate\DtoGenerator;
use Gacela\Console\Application\IdeMeta\IdeMetadataGenerator;
use Gacela\Console\Application\IdeMeta\IdeMetadataScanner;
use Gacela\Console\Domain\AllAppModules\AllAppModulesFinder;
use Gacela\Console\Domain\AllAppModules\AppModuleCreator;
use Gacela\Console\Domain\AllAppModules\ExcludedDirectories;
use Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeFinder;
use Gacela\Console\Domain\CommandArguments\CommandArgumentsParser;
use Gacela\Console\Domain\CommandArguments\CommandArgumentsParserInterface;
use Gacela\Console\Domain\DtoGenerate\DtoClassBuilder;
use Gacela\Console\Domain\DtoGenerate\GeneratedClassPath;
use Gacela\Console\Domain\FileContent\FileContentGenerator;
use Gacela\Console\Domain\FileContent\FileContentGeneratorInterface;
use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use Gacela\Console\Domain\FileContent\JsonFile;
use Gacela\Console\Domain\FileContent\StubFiles;
use Gacela\Console\Domain\FileContent\StubLocator;
use Gacela\Console\Domain\FileContent\StubPublisher;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizerInterface;
use Gacela\Console\Domain\IdeMeta\MetaFileRenderer;
use Gacela\Console\Domain\ModuleGraph\GraphDiffMarkdownFormatter;
use Gacela\Console\Domain\ModuleGraph\GraphFormatterInterface;
use Gacela\Console\Domain\ModuleGraph\GraphvizGraphFormatter;
use Gacela\Console\Domain\ModuleGraph\JsonGraphFormatter;
use Gacela\Console\Domain\ModuleGraph\MermaidGraphFormatter;
use Gacela\Console\Domain\ModuleGraph\ModuleCycleDetector;
use Gacela\Console\Domain\ModuleGraph\ModuleGraphBuilder;
use Gacela\Console\Domain\ModuleGraph\ModuleGraphDiffer;
use Gacela\Console\Domain\ModuleGraph\ModuleRuleChecker;
use Gacela\Console\Domain\ModuleGraph\TextGraphFormatter;
use Gacela\Console\Domain\PackageManifest\ComposerPackage;
use Gacela\Console\Infrastructure\FileContentIo;
use Gacela\Container\ContainerStats;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Dto\Schema\DtoSchema;
use Gacela\Framework\Gacela;
use OuterIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;

use function is_dir;
use function is_string;
use function preg_match;
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
     */
    public function getConsoleCommands(): array
    {
        $commands = [];

        /** @var mixed $command */
        foreach ((array)$this->getProvidedDependency(ConsoleProvider::COMMANDS) as $command) {
            if ($command instanceof Command) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    public function createCommandArgumentsParser(): CommandArgumentsParserInterface
    {
        return new CommandArgumentsParser(
            $this->getConfig()->getComposerJsonContentAsArray(),
        );
    }

    public function createFilenameSanitizer(): FilenameSanitizerInterface
    {
        return new FilenameSanitizer(ResolvableTypes::declaredKinds());
    }

    public function createFileContentGenerator(): FileContentGeneratorInterface
    {
        $declaredKinds = ResolvableTypes::declaredKinds();

        return new FileContentGenerator(
            $this->createFileContentIo(),
            new StubLocator(
                $this->stubsDir(),
                $this->getTemplateByFilenameMap(),
                StubFiles::basic($declaredKinds),
                $declaredKinds,
            ),
        );
    }

    public function createServiceFileContentGenerator(): FileContentGeneratorInterface
    {
        return new FileContentGenerator(
            $this->createFileContentIo(),
            new StubLocator($this->stubsDir(), $this->getServiceTemplateByFilenameMap(), StubFiles::service()),
        );
    }

    /**
     * Where a project's published stubs live, absolute.
     */
    public function stubsDir(): string
    {
        $config = Config::getInstance();
        $configured = $config->getSetupGacela()->getStubsDir();

        return $this->isAbsolutePath($configured)
            ? $configured
            : $config->getAppRootDir() . '/' . $configured;
    }

    /**
     * The built-in stub contents, by the file each is published as.
     *
     * A declared kind is deliberately absent: nothing ships for it, so
     * publishing would only write an empty file where the project has to write
     * the template itself.
     *
     * @return array<string, string>
     */
    public function builtInStubs(): array
    {
        $contents = [];

        foreach (StubFiles::basic() as $filename => $stubFile) {
            $contents[$stubFile] = $this->getTemplateByFilenameMap()[$filename] ?? '';
        }

        foreach (StubFiles::service() as $filename => $stubFile) {
            $contents[$stubFile] = $this->getServiceTemplateByFilenameMap()[$filename] ?? '';
        }

        return $contents;
    }

    public function createStubPublisher(): StubPublisher
    {
        return new StubPublisher($this->createFileContentIo(), $this->builtInStubs());
    }

    public function createAllAppModulesFinder(): AllAppModulesFinder
    {
        return new AllAppModulesFinder(
            $this->createModuleScanIterator(),
            $this->createAppModuleCreator(),
        );
    }

    public function createUndiscoveredFacadeFinder(): UndiscoveredFacadeFinder
    {
        return new UndiscoveredFacadeFinder(
            $this->createModuleScanIterator(),
            $this->facadeSuffixes(),
        );
    }

    public function createDtoGenerator(): DtoGenerator
    {
        return new DtoGenerator(
            new DtoClassBuilder(),
            new GeneratedClassPath(
                $this->rootPsr4Prefixes(),
                Config::getInstance()->getAppRootDir(),
            ),
            $this->createFileContentIo(),
        );
    }

    public function createDtoSchema(): DtoSchema
    {
        return DtoSchema::fromArray(Config::getInstance()->getSetupGacela()->getDtoSchema());
    }

    public function createIdeMetadataGenerator(): IdeMetadataGenerator
    {
        return new IdeMetadataGenerator(
            $this->createAllAppModulesFinder(),
            new IdeMetadataScanner(),
            new MetaFileRenderer(),
            $this->createFileContentIo(),
            Config::getInstance()->getAppRootDir(),
        );
    }

    public function createModuleGraphBuilder(): ModuleGraphBuilder
    {
        return new ModuleGraphBuilder();
    }

    public function createModuleGraphDiffer(): ModuleGraphDiffer
    {
        return new ModuleGraphDiffer();
    }

    public function createModuleCycleDetector(): ModuleCycleDetector
    {
        return new ModuleCycleDetector();
    }

    public function createModuleRuleChecker(): ModuleRuleChecker
    {
        return new ModuleRuleChecker();
    }

    public function createGraphDiffMarkdownFormatter(): GraphDiffMarkdownFormatter
    {
        return new GraphDiffMarkdownFormatter();
    }

    public function createModuleGraphFormatter(string $format): GraphFormatterInterface
    {
        return match ($format) {
            'mermaid' => new MermaidGraphFormatter(),
            'graphviz' => new GraphvizGraphFormatter(),
            'json' => new JsonGraphFormatter(),
            default => new TextGraphFormatter(),
        };
    }

    public function createAppModuleCreator(): AppModuleCreator
    {
        return new AppModuleCreator(
            new FactoryResolver(),
            new ConfigResolver(),
            new ProviderResolver(),
        );
    }

    public function getContainerStats(): ContainerStats
    {
        return $this->getMainContainer()->stats();
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
     * @return array<string,string>
     */
    public function getContainerBindings(): array
    {
        $result = [];
        foreach (Config::getInstance()->getFactory()->createGacelaFileConfig()->getBindings() as $abstract => $concrete) {
            $result[$abstract] = $this->stringifyBoundConcrete($concrete);
        }

        return $result;
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function getContainerContextualBindings(): array
    {
        $result = [];
        foreach (Config::getInstance()->getSetupGacela()->getContextualBindings() as $consumer => $needs) {
            /** @var mixed $concrete */
            foreach ($needs as $abstract => $concrete) {
                $result[$consumer][$abstract] = $this->stringifyBoundConcrete($concrete);
            }
        }

        return $result;
    }

    /**
     * The directories discovery actually walked, as configured rather than as
     * resolved: `appModulePaths` is what a reader would edit, and an absolute
     * path expanded from it is noise next to the entry it came from.
     *
     * An entry that is not a directory is reported all the same. It scans
     * nothing, which is exactly what someone reading an empty module list
     * needs to see -- the `trigger_error` above says so once, on a stream the
     * command's own output may not share.
     *
     * @return list<string>
     */
    public function scannedModulePaths(): array
    {
        return $this->partitionedModulePaths()['scanned'];
    }

    /**
     * The configured entries that are not directories, so discovery skipped
     * them.
     *
     * Listing one under "Scanned" states something that did not happen, to a
     * reader who is looking at an empty module list precisely because it did
     * not. `createModuleScanIterator()` already `trigger_error`s about these,
     * on a stream a command's own output may not share and which a reader
     * scanning a report has no reason to be watching.
     *
     * @return list<string>
     */
    public function unscannedModulePaths(): array
    {
        return $this->partitionedModulePaths()['unscanned'];
    }

    /**
     * @return array{scanned: list<string>, unscanned: list<string>}
     */
    private function partitionedModulePaths(): array
    {
        $paths = Config::getInstance()->getSetupGacela()->getAppModulePaths();
        $rootDir = Gacela::rootDir();

        if ($paths === []) {
            return ['scanned' => [$rootDir], 'unscanned' => []];
        }

        $scanned = [];
        $unscanned = [];

        foreach ($paths as $path) {
            if (is_dir($this->resolveScanPath($path, $rootDir))) {
                $scanned[] = $path;
            } else {
                $unscanned[] = $path;
            }
        }

        return ['scanned' => $scanned, 'unscanned' => $unscanned];
    }

    /**
     * Read from the same place `doctor` reads the rest of the suffix map, rather
     * than from `ResolvableTypes`' static: that one is synced from configuration
     * and a check has no way to know whether it has been yet.
     *
     * @return list<string>
     */
    private function facadeSuffixes(): array
    {
        $configured = Config::getInstance()
            ->getFactory()
            ->createGacelaFileConfig()
            ->getSuffixTypes();

        $suffixes = ResolvableTypes::BUILT_IN[ResolvableTypes::FACADE];
        foreach ($configured[ResolvableTypes::FACADE] ?? [] as $suffix) {
            $suffixes[] = $suffix;
        }

        return array_values(array_unique($suffixes));
    }

    /**
     * A leading separator is not what makes a path absolute on windows: there
     * it is `C:\...`, which starts with neither separator, and a configured
     * absolute directory was being appended to the application root instead.
     */
    private function isAbsolutePath(string $path): bool
    {
        return preg_match('~^(?:[a-zA-Z]:[\\\\/]|[\\\\/])~', $path) === 1;
    }

    /**
     * `mixed` on purpose, and narrower than it looks only from one caller.
     * Bindings are `string|object|callable`, but a *contextual* binding is
     * whatever `->give()` was handed -- typed `mixed` upstream, and an `int`
     * is a normal thing to inject. Psalm sees only the first caller and offers
     * to narrow this to `string|callable|object`, which would fatal on the
     * second. Do not apply that fix.
     */
    private function stringifyBoundConcrete(mixed $concrete): string
    {
        return is_string($concrete) ? $concrete : get_debug_type($concrete);
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
     * The two analysers infer different template arguments for the callback
     * iterator -- psalm reads them off the callable's signature, phpstan's stub
     * does not -- so no single annotation satisfies both. phpstan's view is
     * written here and psalm is suppressed, matching how the caller above
     * already handles the same disagreement.
     *
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement
     *
     * @return RecursiveIteratorIterator<RecursiveCallbackFilterIterator<mixed, mixed, RecursiveDirectoryIterator>>
     */
    private function createRecursiveIteratorFor(string $dir): RecursiveIteratorIterator
    {
        $excluded = new ExcludedDirectories();

        return new RecursiveIteratorIterator(
            // Filtering recursively prunes: returning false for a directory
            // stops the descent, instead of walking it and discarding the
            // leaves afterwards. `$dir` itself is never offered to the filter,
            // so an explicit appModulePaths entry pointing inside one of these
            // still works.
            //
            // hasChildren() is asked of the inner iterator rather than of the
            // current value, because a RecursiveDirectoryIterator yields plain
            // strings under some flag combinations and only this reads the same
            // either way.
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo|string $current, string $key, RecursiveDirectoryIterator $iterator): bool => !$iterator->hasChildren() || !$excluded->isExcluded($iterator->getFilename()),
            ),
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

    /**
     * The application's own psr-4 map, read from its composer.json.
     *
     * The generated classes land where the project already told composer to
     * look, which is what lets the framework register no autoloader for them.
     *
     * @return array<string, string>
     */
    private function rootPsr4Prefixes(): array
    {
        $rootDir = Config::getInstance()->getAppRootDir();
        $decoded = JsonFile::decode($rootDir . DIRECTORY_SEPARATOR . 'composer.json');

        return $decoded === null ? [] : ComposerPackage::autoloadPrefixesOf($decoded);
    }

    private function createFileContentIo(): FileContentIoInterface
    {
        return new FileContentIo();
    }

    /**
     * @return array<string,string>
     */
    private function getTemplateByFilenameMap(): array
    {
        return $this->stringMapDependency(ConsoleProvider::TEMPLATE_BY_FILENAME_MAP);
    }

    /**
     * @return array<string,string>
     */
    private function getServiceTemplateByFilenameMap(): array
    {
        return $this->stringMapDependency(ConsoleProvider::SERVICE_TEMPLATE_BY_FILENAME_MAP);
    }

    /**
     * Narrow a provided dependency to a string map, dropping any entry that is
     * not a string-keyed string. Providers are user-supplied, so the container
     * cannot guarantee the shape on its own.
     *
     * @return array<string,string>
     */
    private function stringMapDependency(string $key): array
    {
        $map = [];

        /** @var mixed $value */
        foreach ((array)$this->getProvidedDependency($key) as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $map[$name] = $value;
            }
        }

        return $map;
    }

    private function getMainContainer(): Container
    {
        return Gacela::container();
    }
}
