<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Exception\ConfigException;
use RuntimeException;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final class Config implements ConfigInterface
{
    private static ?self $instance = null;

    private static ?EventDispatcherInterface $eventDispatcher = null;

    private ?ConfigFactory $configFactory = null;

    private ?string $appRootDir = null;

    /** @var array<string,mixed> */
    private array $config = [];

    // A separate flag, because a legitimately empty merged config is still "initialized";
    // keying off $config === [] re-ran the full init() on every access in that case.
    private bool $initialized = false;

    private ?string $cacheDir = null;

    private function __construct(
        private readonly SetupGacelaInterface $setup,
    ) {
    }

    public static function createWithSetup(SetupGacelaInterface $setup): self
    {
        self::$instance = new self($setup);

        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            throw new RuntimeException('You have to call createWithSetup() first. Have you forgot to bootstrap Gacela?');
        }

        return self::$instance;
    }

    /**
     * @internal
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
        self::$eventDispatcher = null;
    }

    public static function getEventDispatcher(): EventDispatcherInterface
    {
        if (!self::$eventDispatcher instanceof EventDispatcherInterface) {
            self::$eventDispatcher = self::getInstance()
                ->getSetupGacela()
                ->getEventDispatcher();
        }

        return self::$eventDispatcher;
    }

    /**
     * @throws ConfigException
     */
    public function get(string $key, mixed $default = self::DEFAULT_CONFIG_VALUE): mixed
    {
        if (!$this->initialized) {
            $this->init();
        }

        if ($default !== self::DEFAULT_CONFIG_VALUE && !$this->hasKey($key)) {
            return $default;
        }

        if (!$this->hasKey($key)) {
            throw ConfigException::keyNotFound($key, self::class);
        }

        return $this->config[$key];
    }

    /**
     * @throws ConfigException
     */
    public function getString(string $key, ?string $default = null): string
    {
        if (!$this->initialized) {
            $this->init();
        }

        if (!array_key_exists($key, $this->config)) {
            if ($default !== null) {
                return $default;
            }

            throw ConfigException::keyNotFound($key, self::class);
        }

        $value = $this->config[$key];
        if (!is_string($value)) {
            throw ConfigException::invalidType($key, 'string', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function getInt(string $key, ?int $default = null): int
    {
        if (!$this->initialized) {
            $this->init();
        }

        if (!array_key_exists($key, $this->config)) {
            if ($default !== null) {
                return $default;
            }

            throw ConfigException::keyNotFound($key, self::class);
        }

        $value = $this->config[$key];
        if (!is_int($value)) {
            throw ConfigException::invalidType($key, 'int', get_debug_type($value));
        }

        return $value;
    }

    /**
     * Accepts an int value via lossless numeric widening (e.g. 42 -> 42.0).
     *
     * @throws ConfigException
     */
    public function getFloat(string $key, ?float $default = null): float
    {
        if (!$this->initialized) {
            $this->init();
        }

        if (!array_key_exists($key, $this->config)) {
            if ($default !== null) {
                return $default;
            }

            throw ConfigException::keyNotFound($key, self::class);
        }

        $value = $this->config[$key];
        if (is_int($value)) {
            return (float) $value;
        }

        if (!is_float($value)) {
            throw ConfigException::invalidType($key, 'float', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function getBool(string $key, ?bool $default = null): bool
    {
        if (!$this->initialized) {
            $this->init();
        }

        if (!array_key_exists($key, $this->config)) {
            if ($default !== null) {
                return $default;
            }

            throw ConfigException::keyNotFound($key, self::class);
        }

        $value = $this->config[$key];
        if (!is_bool($value)) {
            throw ConfigException::invalidType($key, 'bool', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key,mixed>|null $default
     *
     * @throws ConfigException
     *
     * @return array<array-key,mixed>
     */
    public function getArray(string $key, ?array $default = null): array
    {
        if (!$this->initialized) {
            $this->init();
        }

        if (!array_key_exists($key, $this->config)) {
            if ($default !== null) {
                return $default;
            }

            throw ConfigException::keyNotFound($key, self::class);
        }

        $value = $this->config[$key];
        if (!is_array($value)) {
            throw ConfigException::invalidType($key, 'array', get_debug_type($value));
        }

        return $value;
    }

    /**
     * Return the effective merged configuration (all sources combined).
     *
     * @throws ConfigException
     *
     * @return array<string,mixed>
     */
    public function getAllValues(): array
    {
        if (!$this->initialized) {
            $this->init();
        }

        return $this->config;
    }

    /**
     * Force loading all config values in memory.
     *
     * @throws ConfigException
     */
    public function init(): void
    {
        $this->configFactory = null;

        /** @psalm-suppress DuplicateArrayKey */
        $this->config = [
            ...$this->loadMergedConfigValues(),
            ...$this->setup->getConfigKeyValues(),
        ];

        $this->initialized = true;
    }

    /**
     * @internal persist the merged file-based config values to disk so future
     *           bootstraps skip globbing and parsing configuration files
     *
     * @throws ConfigException
     */
    public function writeMergedConfigCache(): string
    {
        $cache = $this->createMergedConfigCache();
        $cache->write($this->loadAllConfigValues());

        return $cache->filename();
    }

    /**
     * @internal
     */
    public function clearMergedConfigCache(): void
    {
        $this->createMergedConfigCache()->clear();
    }

    /**
     * @internal
     */
    public function mergedConfigCacheFilename(): string
    {
        return $this->createMergedConfigCache()->filename();
    }

    public function setAppRootDir(string $dir): self
    {
        $this->appRootDir = rtrim($dir, DIRECTORY_SEPARATOR);

        if ($this->appRootDir === '' || $this->appRootDir === '0') {
            $this->appRootDir = getcwd() ?: '';
        }

        return $this;
    }

    public function getAppRootDir(): string
    {
        return $this->appRootDir ?? getcwd() ?: '';
    }

    public function getCacheDir(): string
    {
        if ($this->cacheDir !== null) {
            return $this->cacheDir;
        }

        $this->cacheDir = getenv('GACELA_CACHE_DIR') ?: $this->getDefaultCacheDir();

        return rtrim($this->cacheDir, '/\\');
    }

    /**
     * @internal
     */
    public function getFactory(): ConfigFactory
    {
        if (!$this->configFactory instanceof ConfigFactory) {
            $this->configFactory = new ConfigFactory(
                $this->getAppRootDir(),
                $this->setup,
            );
        }

        return $this->configFactory;
    }

    public function getSetupGacela(): SetupGacelaInterface
    {
        return $this->setup;
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * @throws ConfigException
     *
     * @return array<string,mixed>
     */
    private function loadMergedConfigValues(): array
    {
        if (!$this->setup->isFileCacheEnabled()) {
            return $this->loadAllConfigValues();
        }

        $cache = $this->createMergedConfigCache();

        if ($cache->exists()) {
            return $cache->load();
        }

        // Auto-warm on miss so later bootstraps skip re-globbing config files;
        // best-effort, and an empty merged config is not worth caching.
        $merged = $this->loadAllConfigValues();
        if ($merged !== []) {
            $cache->write($merged);
        }

        return $merged;
    }

    private function createMergedConfigCache(): MergedConfigCache
    {
        $env = getenv('APP_ENV');

        return new MergedConfigCache(
            $this->getCacheDir(),
            is_string($env) ? $env : '',
        );
    }

    private function getDefaultCacheDir(): string
    {
        $cacheDir = $this->setup->getFileCacheDirectory();
        if ($cacheDir === '') {
            return sys_get_temp_dir();
        }

        $appRoot = $this->getAppRootDir();

        if (preg_match('#^[A-Za-z]:[\\\\/]#', $cacheDir) === 1) {
            return $cacheDir;
        }

        if ($cacheDir[0] === '/' || $cacheDir[0] === '\\') {
            if (str_starts_with($cacheDir, $appRoot . '/')
                || str_starts_with($cacheDir, $appRoot . '\\')
            ) {
                return $cacheDir;
            }

            return $appRoot . $cacheDir;
        }

        return $appRoot . DIRECTORY_SEPARATOR . $cacheDir;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadAllConfigValues(): array
    {
        return $this->getFactory()
            ->createConfigLoader()
            ->loadAll();
    }
}
