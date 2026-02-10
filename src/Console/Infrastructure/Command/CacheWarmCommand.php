<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\CacheWarm\CacheManager;
use Gacela\Console\Application\CacheWarm\CacheWarmOutputFormatter;
use Gacela\Console\Application\CacheWarm\CacheWarmService;
use Gacela\Console\Application\CacheWarm\ClassNotFoundException;
use Gacela\Console\Application\CacheWarm\ParallelModuleWarmer;
use Gacela\Console\Application\CacheWarm\PerformanceMetrics;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Throwable;

use function count;

/**
 * @method ConsoleFacade getFacade()
 */
final class CacheWarmCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('cache:warm')
            ->setDescription('Pre-resolve all module classes and warm the cache for production')
            ->setHelp($this->getHelpText())
            ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Clear existing cache before warming')
            ->addOption('attributes', 'a', InputOption::VALUE_NONE, 'Pre-scan and cache #[ServiceMap] attributes')
            ->addOption('parallel', 'p', InputOption::VALUE_NONE, 'Use parallel processing with PHP Fibers (PHP 8.1+)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheManager = new CacheManager();
        $cacheWarmService = new CacheWarmService($this->getFacade());
        $formatter = new CacheWarmOutputFormatter($output);
        $metrics = new PerformanceMetrics();

        $formatter->writeHeader();

        $clearCache = (bool) $input->getOption('clear');
        $warmAttributes = (bool) $input->getOption('attributes');
        $useParallel = (bool) $input->getOption('parallel');

        if ($clearCache) {
            $cacheManager->clearCache();
            $formatter->writeCacheCleared();
        }

        $modules = $this->discoverModules($cacheWarmService, $formatter);
        $modules = $cacheWarmService->filterProductionModules($modules);

        $formatter->writeModulesFound($modules);

        if ($useParallel) {
            $parallelWarmer = new ParallelModuleWarmer($cacheWarmService, $formatter);
            [$resolvedCount, $skippedCount] = $parallelWarmer->warmModules($modules, $warmAttributes);
        } else {
            [$resolvedCount, $skippedCount] = $this->warmModulesCache($modules, $cacheWarmService, $formatter, $warmAttributes);
        }

        $formatter->writeSummary(
            count($modules),
            $resolvedCount,
            $skippedCount,
            $metrics->formatElapsedTime(),
            $metrics->formatMemoryUsed(),
        );

        $this->displayCacheInfo($cacheManager, $formatter);

        return Command::SUCCESS;
    }

    /**
     * @return list<AppModule>
     */
    private function discoverModules(
        CacheWarmService $cacheWarmService,
        CacheWarmOutputFormatter $formatter,
    ): array {
        try {
            return $cacheWarmService->discoverModules();
        } catch (Throwable $throwable) {
            $formatter->writeModuleDiscoveryWarning($throwable->getMessage());
            return [];
        }
    }

    /**
     * @param list<AppModule> $modules
     *
     * @return array{int, int}
     */
    private function warmModulesCache(
        array $modules,
        CacheWarmService $cacheWarmService,
        CacheWarmOutputFormatter $formatter,
        bool $warmAttributes,
    ): array {
        $resolvedCount = 0;
        $skippedCount = 0;

        foreach ($modules as $module) {
            $formatter->writeModuleName($module->moduleName());

            $moduleClasses = $cacheWarmService->getModuleClasses($module);

            foreach ($moduleClasses as $classInfo) {
                try {
                    $cacheWarmService->resolveClass($classInfo['className']);

                    if ($warmAttributes) {
                        $cacheWarmService->warmAttributeCache($classInfo['className']);
                    }

                    $formatter->writeClassResolved($classInfo['type'], $classInfo['className']);
                    ++$resolvedCount;
                } catch (ClassNotFoundException) {
                    $formatter->writeClassSkipped($classInfo['type'], $classInfo['className']);
                    ++$skippedCount;
                } catch (Throwable $e) {
                    $formatter->writeClassFailed($classInfo['type'], $classInfo['className'], $e->getMessage());
                    ++$skippedCount;
                }
            }

            $formatter->writeEmptyLine();
        }

        return [$resolvedCount, $skippedCount];
    }

    private function displayCacheInfo(
        CacheManager $cacheManager,
        CacheWarmOutputFormatter $formatter,
    ): void {
        if ($cacheManager->cacheFileExists()) {
            $cacheFile = $cacheManager->getCacheFilePath();
            $cacheSize = $cacheManager->getFormattedCacheFileSize();
            $formatter->writeCacheInfo($cacheFile, $cacheSize);
        } else {
            $formatter->writeCacheWarning();
        }
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
This command pre-resolves all module classes (Facades, Factories, Configs, and Providers)
and populates the Gacela cache for optimal production performance.

<info>What it does:</info>
  - Discovers all modules in your application
  - Resolves each module's Facade, Factory, Config, and Provider classes
  - Generates optimized cache files for class resolution
  - Optionally pre-scans #[ServiceMap] attributes to avoid reflection overhead
  - Reports statistics about the warming process

<info>When to use:</info>
  - During deployment to production
  - After adding new modules
  - After major refactoring
  - When you want to optimize bootstrap performance

<info>Options:</info>
  --clear, -c        Clear existing cache before warming (recommended for fresh start)
  --attributes, -a   Pre-scan and cache #[ServiceMap] attributes for improved performance
  --parallel, -p     Use parallel processing with PHP Fibers (PHP 8.1+) for faster warming

<info>Examples:</info>
  # Warm cache with existing data
  bin/gacela cache:warm

  # Clear and warm cache from scratch
  bin/gacela cache:warm --clear

  # Warm cache with attribute pre-scanning (recommended for production)
  bin/gacela cache:warm --clear --attributes

  # Warm cache with parallel processing for maximum performance
  bin/gacela cache:warm --clear --attributes --parallel

<comment>Note:</comment> This command requires file caching to be enabled in your gacela.php configuration.
If file caching is disabled, the command will still run but won't create persistent cache files.
HELP;
    }
}
