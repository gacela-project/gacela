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
        string $timeTaken,
        string $memoryUsed,
    ): void {
        $this->output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));
        $this->output->writeln('<info>Cache warming complete!</info>');
        $this->output->writeln('');
        $this->output->writeln(sprintf('<fg=cyan>Modules processed:</> %d', $modulesCount));
        $this->output->writeln(sprintf('<fg=cyan>Classes resolved:</> %d', $resolvedCount));
        $this->output->writeln(sprintf('<fg=cyan>Classes skipped:</> %d', $skippedCount));
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

    public function writeCacheWarning(): void
    {
        $this->output->writeln('<fg=yellow>Warning: Cache file was not created. File caching might be disabled.</>');
        $this->output->writeln('<comment>Enable file caching in your gacela.php configuration:</>');
        $this->output->writeln('<comment>  $config->enableFileCache();</>');
        $this->output->writeln('');
    }
}
