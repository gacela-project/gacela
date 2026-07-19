<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\CacheWarm\BytesFormatter;
use Gacela\Console\Application\CacheWarm\CacheManager;
use Gacela\Console\Application\CacheWarm\CacheWarmOutputFormatter;
use Gacela\Console\Application\CacheWarm\CacheWarmService;
use Gacela\Console\Application\CacheWarm\ModuleWarmer;
use Gacela\Console\Application\CacheWarm\PerformanceMetrics;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Cache\CacheWarmedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Throwable;

use function count;
use function file_exists;
use function filesize;

/**
 * @method ConsoleFacade getFacade()
 */
final class CacheWarmCommand extends Command
{
    use EventDispatchingCapabilities;
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('cache:warm')
            ->setDescription('Pre-resolve all module classes and warm the cache for production')
            ->setHelp($this->getHelpText())
            ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Clear existing cache before warming')
            ->addOption('attributes', 'a', InputOption::VALUE_NONE, 'Pre-scan and cache #[ServiceMap] attributes');
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

        if ($clearCache) {
            $cacheManager->clearCache();
            Config::getInstance()->clearMergedConfigCache();
            $formatter->writeCacheCleared();
        }

        $modules = $this->discoverModules($cacheWarmService, $formatter);
        $modules = $cacheWarmService->filterProductionModules($modules);

        $formatter->writeModulesFound($modules);

        AbstractPhpFileCache::beginBatch();

        try {
            $moduleWarmer = new ModuleWarmer($cacheWarmService, $formatter);
            [$resolvedCount, $skippedCount] = $moduleWarmer->warmModules($modules, $warmAttributes);
        } finally {
            AbstractPhpFileCache::commitBatch();
        }

        if (self::shouldDispatch(CacheWarmedEvent::class)) {
            self::dispatchEvent(new CacheWarmedEvent(count($modules), $skippedCount));
        }

        $formatter->writeSummary(
            count($modules),
            $resolvedCount,
            $skippedCount,
            $metrics->formatElapsedTime(),
            $metrics->formatMemoryUsed(),
        );

        $this->displayCacheInfo($cacheManager, $formatter);
        $this->warmAndDisplayMergedConfigCache($formatter);

        return Command::SUCCESS;
    }

    private function warmAndDisplayMergedConfigCache(CacheWarmOutputFormatter $formatter): void
    {
        $filename = Config::getInstance()->writeMergedConfigCache();

        if (!file_exists($filename)) {
            return;
        }

        $formatter->writeMergedConfigCacheInfo($filename, BytesFormatter::format((int) filesize($filename)));
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

<info>Examples:</info>
  # Warm cache with existing data
  bin/gacela cache:warm

  # Clear and warm cache from scratch
  bin/gacela cache:warm --clear

  # Warm cache with attribute pre-scanning (recommended for production)
  bin/gacela cache:warm --clear --attributes

<comment>Note:</comment> This command requires file caching to be enabled in your gacela.php configuration.
If file caching is disabled, the command will still run but won't create persistent cache files.
HELP;
    }
}
