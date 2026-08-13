<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Closure;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;

use function dirname;
use function is_dir;
use function is_writable;
use function sprintf;

/**
 * Whether the cache Gacela was told to keep can actually be written.
 *
 * A cache directory that cannot be written to costs nothing at runtime and
 * everything in production: writing is best-effort by design -- an application
 * must not fail because an optimisation could not be stored -- so a project
 * that enabled caching, deployed, and got a directory it has no permission on
 * runs correctly and pays the cold cost on every request, with nothing said.
 *
 * {@see CacheStalenessCheck} cannot report it either: with no directory there
 * are no cache files to compare against their sources, so it answers "nothing
 * to check" and the run looks healthy.
 *
 * Deliberately read-only, unlike {@see \Gacela\Framework\Cache\WritableDirectory},
 * which creates the directory to find out. A command whose job is to report the
 * state of things should not be the reason that state changed.
 */
final class CacheWritabilityCheck implements HealthCheck
{
    /** @var Closure(string):bool */
    private readonly Closure $isWritable;

    /**
     * @param null|Closure(string):bool $isWritable answers whether a directory that exists can be written to
     */
    public function __construct(
        private readonly bool $fileCacheEnabled,
        private readonly string $cacheDir,
        ?Closure $isWritable = null,
    ) {
        $this->isWritable = $isWritable ?? is_writable(...);
    }

    public function name(): string
    {
        return 'cache directory';
    }

    public function run(): CheckResult
    {
        if (!$this->fileCacheEnabled) {
            return CheckResult::ok($this->name(), 'file cache disabled — nothing is written');
        }

        if ($this->cacheDir === '') {
            return CheckResult::ok($this->name(), 'no cache directory resolved — nothing is written');
        }

        if (is_dir($this->cacheDir)) {
            return ($this->isWritable)($this->cacheDir)
                ? CheckResult::ok($this->name(), sprintf('writable: %s', $this->cacheDir))
                : $this->unwritable(sprintf('%s exists but cannot be written to', $this->cacheDir));
        }

        // Not yet created is normal -- the first write makes it. What matters is
        // whether that write can succeed, which is the parent's business.
        $parent = dirname($this->cacheDir);

        if (is_dir($parent) && ($this->isWritable)($parent)) {
            return CheckResult::ok($this->name(), sprintf('will be created on first write: %s', $this->cacheDir));
        }

        return $this->unwritable(sprintf('%s does not exist and cannot be created', $this->cacheDir));
    }

    private function unwritable(string $detail): CheckResult
    {
        return CheckResult::warn(
            $this->name(),
            [$detail],
            'Caching is enabled but nothing can be stored, so every request pays the cold cost. Grant write permission, or point enableFileCache() at a directory the application user owns.',
        );
    }
}
