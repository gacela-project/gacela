<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Closure;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\Config\MergedConfigCache;
use ReflectionClass;

use function sprintf;

final class CacheStalenessCheck implements HealthCheck
{
    /** @var Closure(string):?string */
    private readonly Closure $sourceFileResolver;

    /**
     * @param null|Closure(string):?string $sourceFileResolver resolves a class-name to its source file path
     * @param null|MergedConfigCache $mergedConfigCache the merged-config cache to check, when there is one
     * @param list<string> $mergedConfigSources every file that contributes to the merged config
     */
    public function __construct(
        private readonly string $cacheDir,
        ?Closure $sourceFileResolver = null,
        private readonly string $appRootDir = '',
        private readonly ?MergedConfigCache $mergedConfigCache = null,
        private readonly array $mergedConfigSources = [],
    ) {
        $this->sourceFileResolver = $sourceFileResolver ?? static function (string $className): ?string {
            if (!class_exists($className) && !interface_exists($className)) {
                return null;
            }

            $file = (new ReflectionClass($className))->getFileName();

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
            $cacheFile = AbstractPhpFileCache::absoluteFilename($this->cacheDir, $filename, $this->appRootDir);
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

        [$mergedStale, $mergedMissing] = $this->mergedConfigStaleness();
        $stale = [...$stale, ...$mergedStale];
        $missing = [...$missing, ...$mergedMissing];

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

    /**
     * The merged configuration cache keeps serving values after a source config
     * file changes, so it can be stale while every class-name entry above is
     * fresh — which is how doctor came to report "all cache entries are fresh"
     * on a stale configuration.
     *
     * The sources are the ones `ConfigLoader` itself would read, so the base
     * patterns, the environment patterns and the local overrides are all
     * covered without this check re-deriving any paths of its own.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function mergedConfigStaleness(): array
    {
        if (!$this->mergedConfigCache instanceof MergedConfigCache || !$this->mergedConfigCache->exists()) {
            return [[], []];
        }

        $cacheMtime = (int) filemtime($this->mergedConfigCache->filename());

        $stale = [];
        $missing = [];

        foreach ($this->mergedConfigSources as $source) {
            if (!is_file($source)) {
                $missing[] = 'merged config ← ' . $source;
                continue;
            }

            if ((int) filemtime($source) > $cacheMtime) {
                $stale[] = 'merged config ← ' . $source;
            }
        }

        return [$stale, $missing];
    }
}
