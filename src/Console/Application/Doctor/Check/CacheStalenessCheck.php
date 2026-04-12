<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Closure;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use ReflectionClass;
use ReflectionException;

use function sprintf;

final class CacheStalenessCheck implements HealthCheck
{
    /** @var Closure(string):?string */
    private readonly Closure $sourceFileResolver;

    /**
     * @param null|Closure(string):?string $sourceFileResolver resolves a class-name to its source file path
     */
    public function __construct(
        private readonly string $cacheDir,
        ?Closure $sourceFileResolver = null,
    ) {
        $this->sourceFileResolver = $sourceFileResolver ?? static function (string $className): ?string {
            if (!class_exists($className) && !interface_exists($className)) {
                return null;
            }
            try {
                $file = (new ReflectionClass($className))->getFileName();
            } catch (ReflectionException) {
                return null;
            }

            return $file === false ? null : $file;
        };
    }

    public function name(): string
    {
        return 'cache staleness';
    }

    public function run(): CheckResult
    {
        if ($this->cacheDir === '' || !is_dir($this->cacheDir)) {
            return CheckResult::ok($this->name(), 'no cache directory — nothing to check');
        }

        $stale = [];
        $missing = [];

        foreach ([ClassNamePhpCache::FILENAME, CustomServicesPhpCache::FILENAME] as $filename) {
            $cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($cacheFile)) {
                continue;
            }

            $cacheMtime = (int) filemtime($cacheFile);
            /** @var array<string,string> $entries */
            $entries = require $cacheFile;

            foreach ($entries as $cacheKey => $className) {
                $source = ($this->sourceFileResolver)($className);
                if ($source === null) {
                    $missing[] = sprintf('%s → %s (source file not found)', $cacheKey, $className);
                    continue;
                }
                if (!is_file($source)) {
                    $missing[] = sprintf('%s → %s (%s)', $cacheKey, $className, $source);
                    continue;
                }
                if ((int) filemtime($source) > $cacheMtime) {
                    $stale[] = sprintf('%s → %s', $cacheKey, $className);
                }
            }
        }

        if ($stale === [] && $missing === []) {
            return CheckResult::ok($this->name(), 'all cache entries are fresh');
        }

        $details = [];
        foreach ($stale as $entry) {
            $details[] = 'stale: ' . $entry;
        }
        foreach ($missing as $entry) {
            $details[] = 'missing source: ' . $entry;
        }

        return CheckResult::warn(
            $this->name(),
            $details,
            'run `bin/gacela cache:clear && bin/gacela cache:warm` to rebuild',
        );
    }
}
