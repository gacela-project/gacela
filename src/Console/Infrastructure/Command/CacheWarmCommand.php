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
use Gacela\Framework\Bootstrap\Package\PackageDiscoveryRegistry;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Cache\CacheWarmedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use Gacela\Framework\Plugins\Membership\MembershipScanner;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Output\OutputInterface;

use Throwable;

use function count;
use function file_exists;
use function filesize;
use function in_array;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
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
            ->addOption('attributes', 'a', InputOption::VALUE_NONE, 'Pre-scan and cache #[ServiceMap] and #[Plugin] attributes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheManager = new CacheManager();
        $cacheWarmService = new CacheWarmService();
        $formatter = new CacheWarmOutputFormatter($output);
        $metrics = new PerformanceMetrics();

        $formatter->writeHeader();

        $clearCache = $input->getOption('clear') === true;
        $warmAttributes = $input->getOption('attributes') === true;

        if ($clearCache && !$this->clearExistingCaches($cacheManager, $formatter)) {
            return Command::FAILURE;
        }

        $writePaths = $cacheManager->warmCacheFilePaths();
        FileCache::resetWriteFailures($writePaths);

        [$modules, $moduleDiscoveryFailed] = $this->discoverModules($formatter);
        $modules = $cacheWarmService->filterProductionModules($modules);

        $formatter->writeModulesFound($modules);

        [$resolvedCount, $skippedCount, $failedCount] = $this->warmInOneBatch(
            new ModuleWarmer($cacheWarmService, $formatter),
            $modules,
            $warmAttributes,
        );

        if (self::shouldDispatch(CacheWarmedEvent::class)) {
            // Named, because three same-typed counters read positionally is what
            // put the skipped count into failedCount in the first place.
            self::dispatchEvent(new CacheWarmedEvent(
                moduleCount: count($modules),
                failedCount: $failedCount,
                skippedCount: $skippedCount,
            ));
        }

        $formatter->writeSummary(
            modulesCount: count($modules),
            resolvedCount: $resolvedCount,
            skippedCount: $skippedCount,
            failedCount: $failedCount,
            timeTaken: $metrics->formatElapsedTime(),
            memoryUsed: $metrics->formatMemoryUsed(),
        );

        $classCacheWritten = $this->displayCacheInfo($cacheManager, $formatter);
        $cacheEnabled = (new GacelaFileCache(Config::getInstance()))->isEnabled();
        $membershipWritten = !$warmAttributes || !$cacheEnabled || $this->warmPluginMembership($formatter);
        if ($warmAttributes && !$cacheEnabled) {
            $formatter->writePluginMembershipSkipped();
        }

        $mergedConfigWritten = !$cacheEnabled || $this->warmAndDisplayMergedConfigCache($formatter);
        $failedWrites = array_values(array_intersect($writePaths, FileCache::failedWrites()));
        if ($cacheEnabled && !$classCacheWritten && !in_array($cacheManager->getCacheFilePath(), $failedWrites, true)) {
            $failedWrites[] = $cacheManager->getCacheFilePath();
        }

        if ($cacheEnabled) {
            foreach ($failedWrites as $filename) {
                $formatter->writeCouldNotWrite($filename);
            }
        }

        $cacheFailed = $cacheEnabled && (!$classCacheWritten || !$membershipWritten || !$mergedConfigWritten || $failedWrites !== []);

        return $moduleDiscoveryFailed || $failedCount > 0 || $cacheFailed ? Command::FAILURE : Command::SUCCESS;
    }

    private function clearExistingCaches(CacheManager $manager, CacheWarmOutputFormatter $formatter): bool
    {
        $failed = [...$manager->clearCache(), ...Config::getInstance()->clearMergedConfigCache()];
        foreach ($failed as $filename) {
            $formatter->writeCouldNotRemove($filename);
        }

        if ($failed !== []) {
            return false;
        }

        $formatter->writeCacheCleared();

        return true;
    }

    /**
     * Batching turns the per-class cache writes into a single atomic write per
     * cache file. It is a write strategy, not a behaviour: the resulting cache
     * is identical either way.
     *
     * @param list<AppModule> $modules
     *
     * @return array{0: int, 1: int, 2: int} [resolvedCount, skippedCount, failedCount]
     */
    private function warmInOneBatch(ModuleWarmer $moduleWarmer, array $modules, bool $warmAttributes): array
    {
        AbstractPhpFileCache::beginBatch();

        try {
            return $moduleWarmer->warmModules($modules, $warmAttributes);
        } finally {
            AbstractPhpFileCache::commitBatch();
        }
    }

    private function warmPluginMembership(CacheWarmOutputFormatter $formatter): bool
    {
        $config = Config::getInstance();
        $setup = $config->getSetupGacela();
        $members = MembershipScanner::forPaths($setup->getAppModulePaths(), $config->getAppRootDir(), $setup->getProjectNamespaces(), PackageDiscoveryRegistry::sources(), PackageDiscoveryRegistry::refusedDirectories())->members();
        $cache = MembershipCache::forScan($config->getCacheDir(), $config->getAppRootDir(), $setup->getAppModulePaths(), $setup->getProjectNamespaces(), PackageDiscoveryRegistry::sources(), PackageDiscoveryRegistry::refusedDirectories());

        if ($members->problems !== []) {
            $formatter->writeMembershipProblems($members->problems);

            return false;
        }

        FileCache::resetWriteFailures([$cache->path()]);
        if (!$cache->write($members)) {
            $formatter->writePluginMembershipWarning($cache->path());

            return false;
        }

        $formatter->writePluginMembershipInfo($cache->path(), count($members->plugins), count($members->tags), count($members->listeners));

        return true;
    }

    private function warmAndDisplayMergedConfigCache(CacheWarmOutputFormatter $formatter): bool
    {
        $config = Config::getInstance();
        FileCache::resetWriteFailures([$config->mergedConfigCacheFilename()]);
        $filename = $config->writeMergedConfigCache();
        if (in_array($filename, FileCache::failedWrites(), true)) {
            $formatter->writeCouldNotWrite($filename);

            return false;
        }

        if (!file_exists($filename)) {
            $formatter->writeMergedConfigCacheSkipped();

            return true;
        }

        $formatter->writeMergedConfigCacheInfo($filename, BytesFormatter::format((int) filesize($filename)));

        return true;
    }

    /**
     * @return array{list<AppModule>, bool} modules and whether discovery failed
     */
    private function discoverModules(
        CacheWarmOutputFormatter $formatter,
    ): array {
        try {
            return [$this->getFacade()->findAllAppModules(), false];
        } catch (Throwable $throwable) {
            $formatter->writeModuleDiscoveryWarning($throwable->getMessage());
            return [[], true];
        }
    }

    private function displayCacheInfo(
        CacheManager $cacheManager,
        CacheWarmOutputFormatter $formatter,
    ): bool {
        if ($cacheManager->cacheFileExists() && !in_array($cacheManager->getCacheFilePath(), FileCache::failedWrites(), true)) {
            $cacheFile = $cacheManager->getCacheFilePath();
            $cacheSize = $cacheManager->getFormattedCacheFileSize();
            $formatter->writeCacheInfo($cacheFile, $cacheSize);

            return true;
        }

        $formatter->writeCacheWarning();

        return false;
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
  - Optionally pre-scans #[ServiceMap] attributes to avoid reflection overhead,
    and the #[Plugin] classes that join plugin stacks to avoid a scan at runtime
  - Reports statistics about the warming process

<info>When to use:</info>
  - During deployment to production
  - After adding new modules
  - After major refactoring
  - When you want to optimize bootstrap performance

<info>Options:</info>
  --clear, -c        Clear existing cache before warming (recommended for fresh start)
  --attributes, -a   Pre-scan and cache #[ServiceMap] and #[Plugin] attributes for improved performance

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
