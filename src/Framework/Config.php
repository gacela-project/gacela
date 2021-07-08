<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\ConfigReader\EnvConfigReader;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\ConfigReaderException;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaJsonConfig;
use Gacela\Framework\Config\GacelaJsonConfigItem;
use Gacela\Framework\Exception\ConfigException;

final class Config
{
    private const GACELA_CONFIG_FILENAME = 'gacela.json';

    private static string $applicationRootDir = '';

    private static ?self $instance = null;

    /** @var array<string,ConfigReaderInterface> */
    private array $readers;

    private array $config = [];

    /**
     * @param array<string,ConfigReaderInterface> $readers
     */
    private function __construct(array $readers)
    {
        $this->readers = $readers;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self([
                'php' => new PhpConfigReader(),
                'env' => new EnvConfigReader(),
            ]);
        }

        return self::$instance;
    }

    public static function getApplicationRootDir(): string
    {
        if (empty(self::$applicationRootDir)) {
            self::$applicationRootDir = getcwd() ?: '';
        }

        return self::$applicationRootDir;
    }

    public static function setApplicationRootDir(string $dir): void
    {
        self::$applicationRootDir = $dir;
    }

    /**
     * @param null|mixed $default
     *
     * @throws ConfigException
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (empty($this->config)) {
            $this->init();
        }

        if ($default !== null && !$this->hasValue($key)) {
            return $default;
        }

        if (!$this->hasValue($key)) {
            throw ConfigException::keyNotFound($key, self::class);
        }

        return $this->config[$key];
    }

    /**
     * @throws ConfigException
     */
    public function init(): void
    {
        $gacelaJson = $this->createGacelaJsonConfig();
        $configs = [];

        foreach ($this->scanAllConfigFiles($gacelaJson) as $absolutePath) {
            $configs[] = $this->readConfigFromFile($gacelaJson, $absolutePath);
        }

        $configs[] = $this->loadLocalConfigFile($gacelaJson);

        $this->config = array_merge(...$configs);
    }

    private function hasValue(string $key): bool
    {
        return isset($this->config[$key]);
    }

    private function createGacelaJsonConfig(): GacelaJsonConfig
    {
        $gacelaJsonPath = self::getApplicationRootDir() . '/' . self::GACELA_CONFIG_FILENAME;

        if (is_file($gacelaJsonPath)) {
            /** @psalm-suppress MixedArgumentTypeCoercion */
            return GacelaJsonConfig::fromArray(
                (array)json_decode(file_get_contents($gacelaJsonPath), true)
            );
        }

        return GacelaJsonConfig::withDefaults();
    }

    /**
     * @throws ConfigException
     *
     * @return string[]
     */
    private function scanAllConfigFiles(GacelaJsonConfig $gacelaJsonConfig): array
    {
        $configGroup = array_map(
            fn (GacelaJsonConfigItem $config): array => array_map(
                static fn ($p): string => (string)$p,
                array_diff(
                    glob($this->generateAbsolutePath($config->path())),
                    [$this->generateAbsolutePath($config->pathLocal())]
                )
            ),
            $gacelaJsonConfig->configs()
        );

        return array_merge(...$configGroup);
    }

    private function readConfigFromFile(GacelaJsonConfig $gacelaJson, string $absolutePath): array
    {
        $result = [];

        foreach ($gacelaJson->configs() as $config) {
            $result[] = $this->readConfigItem($config, $absolutePath);
        }

        return array_merge(...array_filter($result));
    }

    private function loadLocalConfigFile(GacelaJsonConfig $gacelaJson): array
    {
        $result = [];

        foreach ($gacelaJson->configs() as $config) {
            $configLocalAbsolutePath = $this->generateAbsolutePath($config->pathLocal());

            if (is_file($configLocalAbsolutePath)) {
                $result[] = $this->readConfigItem($config, $configLocalAbsolutePath);
            }
        }

        return array_merge(...array_filter($result));
    }

    private function generateAbsolutePath(string $relativePath): string
    {
        return sprintf(
            '%s/%s',
            self::getApplicationRootDir(),
            $relativePath
        );
    }

    private function readConfigItem(GacelaJsonConfigItem $config, string $absolutePath): array
    {
        $reader = $this->readers[$config->type()] ?? null;

        if ($reader === null) {
            throw ConfigReaderException::notSupported($config->type(), $this->readers);
        }

        if ($reader->canRead($absolutePath)) {
            return $reader->read($absolutePath);
        }

        return [];
    }
}
