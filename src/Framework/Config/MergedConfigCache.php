<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Cache\FileCache;

use function sha1;
use function substr;

final class MergedConfigCache
{
    public const FILENAME_PREFIX = 'gacela-merged-config';

    public const FILENAME_EXTENSION = '.php';

    public function __construct(
        private readonly string $cacheDir,
        private readonly string $env = '',
        private readonly string $appRootDir = '',
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
     * @param array<string,mixed> $data
     */
    public function write(array $data): void
    {
        FileCache::writeAtomically($this->filename(), $data);
    }

    public function clear(): void
    {
        FileCache::delete($this->filename());

        // Also drop a cache written before filenames were app-scoped, so
        // clearing leaves no stale pre-#465 file behind in a shared dir.
        $legacyFilename = $this->buildFilename('');
        if ($legacyFilename !== $this->filename()) {
            FileCache::delete($legacyFilename);
        }
    }

    /**
     * The cache dir can be shared between apps (it defaults to the system
     * temp dir), so the filename embeds a hash of the app root: without it,
     * every app using the shared default read and wrote the same file and
     * silently served another app's merged config.
     */
    public function filename(): string
    {
        $appSuffix = $this->appRootDir !== ''
            ? '-' . substr(sha1($this->appRootDir), 0, 12)
            : '';

        return $this->buildFilename($appSuffix);
    }

    private function buildFilename(string $appSuffix): string
    {
        $envSuffix = $this->env !== '' ? '-' . $this->env : '';

        return $this->cacheDir
            . DIRECTORY_SEPARATOR
            . self::FILENAME_PREFIX
            . $appSuffix
            . $envSuffix
            . self::FILENAME_EXTENSION;
    }
}
