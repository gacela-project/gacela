<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\ServiceResolverAwareTrait;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Throwable;

use function count;
use function file_exists;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
final class CacheWarmCommand extends Command
{
    use ServiceResolverAwareTrait;

    #[Override]
    protected function configure(): void
    {
        $this->setName('cache:warm')
            ->setDescription('Pre-resolve all module classes and warm the cache for production')
            ->setHelp($this->getHelpText())
            ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Clear existing cache before warming');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>Warming Gacela cache...</info>');
        $output->writeln('<info>' . str_repeat('=', 60) . '</info>');
        $output->writeln('');

        $clearCache = (bool) $input->getOption('clear');

        if ($clearCache) {
            $this->clearCache($output);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Find all modules
        try {
            $modules = $this->getFacade()->findAllAppModules();
        } catch (Throwable $throwable) {
            // If module discovery fails (e.g., due to test fixtures), continue with empty set
            $output->writeln('<fg=yellow>Warning: Some modules could not be discovered due to errors</>');
            $output->writeln(sprintf('  Error: %s', $throwable->getMessage()));
            $modules = [];
        }

        // Filter out test/fixture modules that might cause issues
        $modules = array_filter($modules, static function ($module): bool {
            $className = $module->facadeClass();
            return !str_contains($className, 'Test')
                && !str_contains($className, '\\Fixtures\\')
                && !str_contains($className, '\\Benchmark\\');
        });

        $output->writeln(sprintf('<fg=cyan>Found %d modules</>', count($modules)));
        $output->writeln('');

        $resolvedCount = 0;
        $skippedCount = 0;

        // Pre-resolve all module classes
        foreach ($modules as $module) {
            $moduleClasses = [
                'Facade' => $module->facadeClass(),
                'Factory' => $module->factoryClass(),
                'Config' => $module->configClass(),
                'Provider' => $module->providerClass(),
            ];

            $output->writeln(sprintf('<comment>Processing:</> %s', $module->moduleName()));

            foreach ($moduleClasses as $type => $className) {
                if ($className === null) {
                    continue;
                }

                if (!class_exists($className)) {
                    $output->writeln(sprintf('  <fg=yellow>⚠ Skipped %s:</> %s (class not found)', $type, $className));
                    ++$skippedCount;
                    continue;
                }

                // Trigger class resolution to populate cache
                try {
                    class_exists($className, true);
                    $output->writeln(sprintf('  <fg=green>✓ Resolved %s:</> %s', $type, $className));
                    ++$resolvedCount;
                } catch (Throwable $e) {
                    $output->writeln(sprintf('  <fg=red>✗ Failed %s:</> %s (%s)', $type, $className, $e->getMessage()));
                    ++$skippedCount;
                }
            }

            $output->writeln('');
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $output->writeln('<info>' . str_repeat('=', 60) . '</info>');
        $output->writeln('<info>Cache warming complete!</info>');
        $output->writeln('');
        $output->writeln(sprintf('<fg=cyan>Modules processed:</> %d', count($modules)));
        $output->writeln(sprintf('<fg=cyan>Classes resolved:</> %d', $resolvedCount));
        $output->writeln(sprintf('<fg=cyan>Classes skipped:</> %d', $skippedCount));
        $output->writeln(sprintf('<fg=cyan>Time taken:</> %.3f seconds', $endTime - $startTime));
        $output->writeln(sprintf('<fg=cyan>Memory used:</> %s', $this->formatBytes($endMemory - $startMemory)));
        $output->writeln('');

        $this->displayCacheInfo($output);

        return Command::SUCCESS;
    }

    private function clearCache(OutputInterface $output): void
    {
        $cacheDir = Config::getInstance()->getCacheDir();
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . ClassNamePhpCache::FILENAME;

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $output->writeln('<fg=yellow>Cleared existing cache</>');
            $output->writeln('');
        }
    }

    private function displayCacheInfo(OutputInterface $output): void
    {
        $cacheDir = Config::getInstance()->getCacheDir();
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . ClassNamePhpCache::FILENAME;

        if (file_exists($cacheFile)) {
            $output->writeln(sprintf('<fg=cyan>Cache file:</> %s', $cacheFile));
            $output->writeln(sprintf('<fg=cyan>Cache size:</> %s', $this->formatBytes((int) filesize($cacheFile))));
        } else {
            $output->writeln('<fg=yellow>Warning: Cache file was not created. File caching might be disabled.</>');
            $output->writeln('<comment>Enable file caching in your gacela.php configuration:</>');
            $output->writeln('<comment>  $config->enableFileCache();</>');
        }

        $output->writeln('');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return sprintf('%d B', $bytes);
        }

        if ($bytes < 1048576) {
            return sprintf('%.2f KB', $bytes / 1024);
        }

        return sprintf('%.2f MB', $bytes / 1048576);
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
  - Reports statistics about the warming process

<info>When to use:</info>
  - During deployment to production
  - After adding new modules
  - After major refactoring
  - When you want to optimize bootstrap performance

<info>Options:</info>
  --clear, -c    Clear existing cache before warming (recommended for fresh start)

<info>Examples:</info>
  # Warm cache with existing data
  bin/gacela cache:warm

  # Clear and warm cache from scratch
  bin/gacela cache:warm --clear

<comment>Note:</comment> This command requires file caching to be enabled in your gacela.php configuration.
If file caching is disabled, the command will still run but won't create persistent cache files.
HELP;
    }
}
