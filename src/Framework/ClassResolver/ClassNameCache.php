<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

final class ClassNameCache implements ClassNameCacheInterface
{
    private const CACHED_CLASS_NAMES_FILE = '.gacela-class-names.cache';

    /** @var array<string,string> */
    private static array $cachedClassNames = [];

    private string $cachedClassNamesDir;

    public function __construct(string $cachedClassNamesDir)
    {
        $this->cachedClassNamesDir = $cachedClassNamesDir;

        self::$cachedClassNames = $this->getCachedClassNames();
    }

    /**
     * @internal
     */
    public static function resetCachedClassNames(): void
    {
        self::$cachedClassNames = [];
    }

    public function has(string $cacheKey): bool
    {
        return isset(self::$cachedClassNames[$cacheKey]);
    }

    public function get(string $cacheKey): string
    {
        return self::$cachedClassNames[$cacheKey];
    }

    public function put(string $cacheKey, string $className): void
    {
        self::$cachedClassNames[$cacheKey] = $className;

        $fileContent = sprintf(
            '<?php return %s;',
            var_export(self::$cachedClassNames, true)
        );

        file_put_contents($this->getCachedFilename(), $fileContent);
    }

    /**
     * @return array<string,string>
     */
    private function getCachedClassNames(): array
    {
        $filename = $this->getCachedFilename();

        if (file_exists($filename)) {
            /** @var array<string,string> $content */
            $content = require $filename;

            return $content;
        }

        return [];
    }

    private function getCachedFilename(): string
    {
        return $this->cachedClassNamesDir . self::CACHED_CLASS_NAMES_FILE;
    }
}
