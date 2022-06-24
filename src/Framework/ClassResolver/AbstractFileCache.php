<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use RuntimeException;

abstract class AbstractFileCache implements ClassNameCacheInterface
{
    /** @var array<string,string> */
    protected static array $cache = [];

    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
        self::$cache = $this->getExistingCache();
    }

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::$cache = [];
    }

    public function has(string $cacheKey): bool
    {
        return isset(self::$cache[$cacheKey]);
    }

    public function get(string $cacheKey): string
    {
        return self::$cache[$cacheKey];
    }

    public function put(string $cacheKey, string $className): void
    {
        self::$cache[$cacheKey] = $className;

        $fileContent = sprintf(
            '<?php return %s;',
            var_export(self::$cache, true)
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
            && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        return $this->cacheDir . '/' . $this->getCacheFilename();
    }
}
