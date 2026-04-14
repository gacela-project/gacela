<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use RuntimeException;

use function sprintf;

use const LOCK_EX;

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
     * @param array<string,mixed> $data
     */
    public function write(array $data): void
    {
        $this->ensureCacheDir();

        $content = sprintf('<?php return %s;', var_export($data, true));
        file_put_contents($this->filename(), $content, LOCK_EX);
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

        if (!mkdir($concurrentDirectory = $this->cacheDir, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
    }
}
