<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Cache\FileCache;

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
        FileCache::writeAtomically($this->filename(), $data);
    }

    public function clear(): void
    {
        FileCache::delete($this->filename());
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
}
