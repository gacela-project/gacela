<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Cache\FileCache;
use RuntimeException;

use function sprintf;

final class MergedConfigCache
{
    public const FILENAME_PREFIX = 'gacela-merged-config';

    public const FILENAME_EXTENSION = '.php';

    public function __construct(
        private readonly string $cacheDir,
        private readonly string $env = '',
    ) {
    }

    public function exists(): bool
    {
        return is_file($this->filename());
    }

    /**
     * @return array<string,mixed>
     */
    public function load(): array
    {
        /**
         * @psalm-suppress UnresolvableInclude
         *
         * @var array<string,mixed> $data
         */
        $data = require $this->filename();

        return $data;
    }

    /**
     * Persist the merged config, failing loud: callers that must not fatal on
     * an unusable cache directory (the bootstrap auto-warm) gate on
     * {@see \Gacela\Framework\Cache\WritableDirectory::isUsable()} before
     * calling this, while the explicit `cache:warm` command keeps the error.
     *
     * @param array<string,mixed> $data
     *
     * @throws RuntimeException when the cache directory or file cannot be written
     */
    public function write(array $data): void
    {
        $this->ensureCacheDir();

        if (!FileCache::writeAtomically($this->filename(), $data)) {
            throw new RuntimeException(sprintf('Cache file "%s" was not written', $this->filename()));
        }
    }

    public function clear(): void
    {
        if ($this->exists()) {
            unlink($this->filename());
        }
    }

    public function filename(): string
    {
        $suffix = $this->env !== '' ? '-' . $this->env : '';

        return $this->cacheDir
            . DIRECTORY_SEPARATOR
            . self::FILENAME_PREFIX
            . $suffix
            . self::FILENAME_EXTENSION;
    }

    private function ensureCacheDir(): void
    {
        if (is_dir($this->cacheDir)) {
            return;
        }

        // Suppressed: the thrown exception already carries the failure; the
        // raw mkdir() warning would only add noise on the CLI.
        if (!@mkdir($this->cacheDir, recursive: true) && !is_dir($this->cacheDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->cacheDir));
        }
    }
}
