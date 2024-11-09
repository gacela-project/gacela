<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use RuntimeException;

use function sprintf;

abstract class AbstractPhpFileCache implements CacheInterface
{
    /** @var array<class-string,array<string,string>> */
    private static array $cache = [];

    public function __construct(
        private readonly string $cacheDir,
    ) {
        self::$cache[static::class] = $this->getExistingCache();
    }

    /**
     * @internal
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        return self::$cache[static::class];
    }

    public function has(string $cacheKey): bool
    {
        return isset(self::$cache[static::class][$cacheKey]);
    }

    public function get(string $cacheKey): string
    {
        return self::$cache[static::class][$cacheKey];
    }

    /**
     * @return array<string,string>
     */
    public function getAll(): array
    {
        return self::$cache[static::class];
    }

    public function put(string $cacheKey, string $className): void
    {
        self::$cache[static::class][$cacheKey] = $className;

        $fileContent = sprintf(
            '<?php return %s;',
            var_export(self::$cache[static::class], true),
        );

        file_put_contents($this->getAbsoluteCacheFilename(), $fileContent);
    }

    abstract protected function getCacheFilename(): string;

    /**
     * @return array<string,string>
     */
    private function getExistingCache(): array
    {
        $filename = $this->getAbsoluteCacheFilename();

        if (file_exists($filename)) {
            /** @var array<string,string> $content */
            $content = require $filename;

            return $content;
        }

        return [];
    }

    private function getAbsoluteCacheFilename(): string
    {
        if (!is_dir($this->cacheDir)
            && !mkdir($concurrentDirectory = $this->cacheDir, 0777, true)
            && !is_dir($concurrentDirectory)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        return $this->cacheDir . DIRECTORY_SEPARATOR . $this->getCacheFilename();
    }
}
