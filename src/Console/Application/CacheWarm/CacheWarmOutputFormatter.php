<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;
use function str_repeat;

final class CacheWarmOutputFormatter
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {
    }

    public function writeHeader(): void
    {
        $this->output->writeln('');
        $this->output->writeln('<info>Warming Gacela cache...</info>');
        $this->output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));
        $this->output->writeln('');
    }

    public function writeCacheCleared(): void
    {
        $this->output->writeln('<fg=yellow>Cleared existing cache</>');
        $this->output->writeln('');
    }

    public function writeModuleDiscoveryWarning(string $errorMessage): void
    {
        $this->output->writeln('<fg=yellow>Warning: Some modules could not be discovered due to errors</>');
        $this->output->writeln(sprintf('  Error: %s', $errorMessage));
    }

    /**
     * @param list<mixed> $modules
     */
    public function writeModulesFound(array $modules): void
    {
        $this->output->writeln(sprintf('<fg=cyan>Found %d modules</>', count($modules)));
        $this->output->writeln('');
    }

    public function writeModuleName(string $moduleName): void
    {
        $this->output->writeln(sprintf('<comment>Processing:</> %s', $moduleName));
    }

    public function writeClassResolved(string $type, string $className): void
    {
        $this->output->writeln(sprintf('  <fg=green>✓ Resolved %s:</> %s', $type, $className));
    }

    public function writeClassSkipped(string $type, string $className): void
    {
        $this->output->writeln(sprintf('  <fg=yellow>⚠ Skipped %s:</> %s (class not found)', $type, $className));
    }

    public function writeClassFailed(string $type, string $className, string $errorMessage): void
    {
        $this->output->writeln(sprintf('  <fg=red>✗ Failed %s:</> %s (%s)', $type, $className, $errorMessage));
    }

    public function writeEmptyLine(): void
    {
        $this->output->writeln('');
    }

    public function writeSummary(
        int $modulesCount,
        int $resolvedCount,
        int $skippedCount,
        int $failedCount,
        string $timeTaken,
        string $memoryUsed,
    ): void {
        $this->output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));
        $this->output->writeln('<info>Cache warming complete!</info>');
        $this->output->writeln('');
        $this->output->writeln(sprintf('<fg=cyan>Modules processed:</> %d', $modulesCount));
        $this->output->writeln(sprintf('<fg=cyan>Classes resolved:</> %d', $resolvedCount));
        $this->output->writeln(sprintf('<fg=cyan>Classes skipped:</> %d', $skippedCount));
        $this->output->writeln(sprintf('<fg=cyan>Classes failed:</> %d', $failedCount));
        $this->output->writeln(sprintf('<fg=cyan>Time taken:</> %s', $timeTaken));
        $this->output->writeln(sprintf('<fg=cyan>Memory used:</> %s', $memoryUsed));
        $this->output->writeln('');
    }

    public function writeCacheInfo(string $cacheFile, string $cacheSize): void
    {
        $this->output->writeln(sprintf('<fg=cyan>Cache file:</> %s', $cacheFile));
        $this->output->writeln(sprintf('<fg=cyan>Cache size:</> %s', $cacheSize));
        $this->output->writeln('');
    }

    /**
     * Silent before, so a script that edits config and warms in one go could
     * not tell that the cache it asked for is not there yet.
     */
    public function writeMergedConfigCacheSkipped(): void
    {
        $this->output->writeln('<comment>Merged config cache: not written, since a config source changed this second or the cache directory is not writable. The next bootstrap writes it.</comment>');
        $this->output->writeln('');
    }

    public function writeMergedConfigCacheInfo(string $cacheFile, string $cacheSize): void
    {
        $this->output->writeln(sprintf('<fg=cyan>Merged config cache:</> %s', $cacheFile));
        $this->output->writeln(sprintf('<fg=cyan>Merged config size:</> %s', $cacheSize));
        $this->output->writeln('');
    }

    public function writePluginMembershipInfo(string $cacheFile, int $pluginCount, int $tagCount, int $listenerCount): void
    {
        $this->output->writeln(sprintf('<fg=cyan>Membership cache:</> %s', $cacheFile));
        $this->output->writeln(sprintf('<fg=cyan>#[Plugin] classes:</> %d', $pluginCount));
        $this->output->writeln(sprintf('<fg=cyan>#[Tag] classes:</> %d', $tagCount));
        $this->output->writeln(sprintf('<fg=cyan>#[AsListener] methods:</> %d', $listenerCount));
        $this->output->writeln('');
    }

    /**
     * @param list<string> $problems
     */
    public function writeMembershipProblems(array $problems): void
    {
        $this->output->writeln('<fg=yellow>Warning: the membership cache was not written:</>');
        foreach ($problems as $problem) {
            $this->output->writeln('  ' . $problem);
        }

        $this->output->writeln('');
    }

    public function writePluginMembershipWarning(string $cacheFile): void
    {
        $this->output->writeln(sprintf('<fg=yellow>Warning: could not write the membership cache to %s.</>', $cacheFile));
        $this->output->writeln('<comment>Plugin stacks, tags and module events will scan for their attributes on first use instead.</>');
        $this->output->writeln('');
    }

    public function writeCacheWarning(): void
    {
        $this->output->writeln('<fg=yellow>Warning: Cache file was not created. File caching might be disabled.</>');
        $this->output->writeln('<comment>Enable file caching in your gacela.php configuration:</>');
        $this->output->writeln('<comment>  $config->enableFileCache();</>');
        $this->output->writeln('');
    }
}
