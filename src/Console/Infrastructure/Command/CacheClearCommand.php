<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\CacheWarm\CacheManager;
use Gacela\Console\ConsoleFacade;
use Gacela\Container\Container as GacelaContainer;
use Gacela\Framework\Config\Config;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class CacheClearCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('cache:clear')
            ->setDescription('Clear all Gacela cache files')
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheManager = new CacheManager();
        $config = Config::getInstance();
        $mergedConfigCacheFile = $config->mergedConfigCacheFilename();
        $mergedConfigCacheExists = file_exists($mergedConfigCacheFile);

        $output->writeln('<info>Clearing Gacela cache...</info>');
        $output->writeln('');

        // The container memoizes reflection output -- property plans, #[Lazy],
        // #[Singleton]/#[Factory], instantiability -- statically, so it outlives
        // every container and no file on disk holds it. Cleared before the
        // file-count check, since "no cache files found" says nothing about the
        // in-process memos, and unconditionally because clearing caches is this
        // command's whole job. Free here, where the process ends a moment later;
        // Gacela::resetCache() deliberately skips it, running per bootstrap for
        // memos that never go stale.
        GacelaContainer::resetStaticCaches();

        $clearedFiles = $cacheManager->getExistingCacheFilesWithSize();

        if ($clearedFiles === [] && !$mergedConfigCacheExists) {
            $output->writeln('<comment>No cache files found.</comment>');
            return Command::SUCCESS;
        }

        $cacheManager->clearCache();

        foreach ($clearedFiles as $cacheFile => $cacheSize) {
            $output->writeln(sprintf(
                '<info>✓</info> Cleared cache file: <comment>%s</comment> (<comment>%s</comment>)',
                $cacheFile,
                $cacheSize,
            ));
        }

        if ($mergedConfigCacheExists) {
            $config->clearMergedConfigCache();

            $output->writeln(sprintf(
                '<info>✓</info> Cleared merged config cache: <comment>%s</comment>',
                $mergedConfigCacheFile,
            ));
        }

        $output->writeln('');
        $output->writeln('<info>Cache cleared successfully!</info>');

        return Command::SUCCESS;
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
This command clears all Gacela cache files including class resolution cache,
custom services cache, and any other cached data.

<info>What it does:</info>
  - Removes all generated cache files
  - Clears class resolution cache
  - Clears custom services cache
  - Reports the size of cleared cache

<info>When to use:</info>
  - After modifying module structure
  - When experiencing unexpected behavior
  - Before running cache:warm for a fresh start
  - During development to ensure fresh state

<info>Examples:</info>
  # Clear all cache
  bin/gacela cache:clear

<comment>Note:</comment> After clearing cache, consider running cache:warm to regenerate
optimized cache files for production use.
HELP;
    }
}
