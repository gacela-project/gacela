<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\Schema\ConfigSchema;
use Gacela\Framework\Config\Schema\ConfigSchemaViolation;
use Gacela\Framework\Event\Config\ConfigInitializedEvent;
use Gacela\Framework\Event\Config\ConfigKeyNotFoundEvent;
use Gacela\Framework\Event\Config\ConfigKeyReadEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\EventDispatcherProvider;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Exception\ConfigException;
use RuntimeException;

use function array_key_exists;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final class Config implements ConfigInterface
{
    use EventDispatchingCapabilities;

    private static ?self $instance = null;

    private ?ConfigFactory $configFactory = null;

    private ?string $appRootDir = null;

    /** @var array<string,mixed> */
    private array $config = [];

    // A separate flag, because a legitimately empty merged config is still "initialized";
    // keying off $config === [] re-ran the full init() on every access in that case.
    private bool $initialized = false;

    private ?string $cacheDir = null;

    private ?ConfigSchema $configSchema = null;

    private function __construct(
        private readonly SetupGacelaInterface $setup,
    ) {
    }

    public static function createWithSetup(SetupGacelaInterface $setup): self
    {
        self::$instance = new self($setup);
        EventDispatcherProvider::setResolver(static fn (): EventDispatcherInterface => $setup->getEventDispatcher());

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
        EventDispatcherProvider::reset();
    }

    public static function getEventDispatcher(): EventDispatcherInterface
    {
        return EventDispatcherProvider::get();
    }

    /**
     * @throws ConfigException
     */
    public function get(string $key, mixed $default = self::DEFAULT_CONFIG_VALUE): mixed
    {
        if (!$this->initialized) {
            $this->init();
        }

        if (self::shouldDispatch(ConfigKeyReadEvent::class)) {
            self::dispatchEvent(new ConfigKeyReadEvent($key));
        }

        if ($default !== self::DEFAULT_CONFIG_VALUE && !$this->hasKey($key)) {
            $this->notifyKeyNotFound($key);

            return $default;
        }

        if (!$this->hasKey($key)) {
            $this->notifyKeyNotFound($key);

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

        // Declared defaults come first: a key a source provides is that
        // source's, and a key nobody provides is the declaration's.
        /** @psalm-suppress DuplicateArrayKey */
        $this->config = [
            ...$this->configSchema()->defaults(),
            ...$this->loadMergedConfigValues(),
            ...$this->setup->getConfigKeyValues(),
        ];

        $this->initialized = true;

        if ($this->setup->shouldValidateConfigSchemaOnBoot()) {
            $this->assertConfigMatchesSchema();
        }

        if (self::shouldDispatch(ConfigInitializedEvent::class)) {
            self::dispatchEvent(new ConfigInitializedEvent(count($this->config)));
        }
    }

    /**
     * What the application declared its configuration should contain.
     *
     * Built once and kept: `validate:config`, `doctor` and `debug:config` all
     * ask the same question of the same declaration.
     */
    public function configSchema(): ConfigSchema
    {
        return $this->configSchema ??= ConfigSchema::fromArray($this->setup->getConfigSchema());
    }

    /**
     * Every declared key the merged configuration does not satisfy.
     *
     * @return list<ConfigSchemaViolation>
     */
    public function configSchemaViolations(): array
    {
        return $this->configSchema()->violations($this->getAllValues());
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
     */
    private function assertConfigMatchesSchema(): void
    {
        $violations = $this->configSchema()->violations($this->config);
        if ($violations === []) {
            return;
        }

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->message;
        }

        throw ConfigException::schemaViolations($messages);
    }

    private function notifyKeyNotFound(string $key): void
    {
        if (self::shouldDispatch(ConfigKeyNotFoundEvent::class)) {
            self::dispatchEvent(new ConfigKeyNotFoundEvent($key));
        }
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
        return new MergedConfigCache(
            $this->getCacheDir(),
            AppEnv::current(),
            $this->getAppRootDir(),
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
