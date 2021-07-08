<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\GacelaJsonConfig;
use Gacela\Framework\Config\GacelaJsonConfigItem;
use Gacela\Framework\Config\ReaderFactory;
use Gacela\Framework\Exception\ConfigException;

final class Config
{
    private const GACELA_CONFIG_FILENAME = 'gacela.json';

    private static string $applicationRootDir = '';

    private static array $config = [];

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param null|mixed $default
     *
     * @throws ConfigException
     *
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if (empty(self::$config)) {
            self::init();
        }

        if (null !== $default && !self::hasValue($key)) {
            return $default;
        }

        if (!self::hasValue($key)) {
            throw ConfigException::keyNotFound($key, self::class);
        }

        return self::$config[$key];
    }

    /**
     * @throws ConfigException
     */
    public static function init(): void
    {
        $gacelaJson =  self::createGacelaJsonConfig();
        $configs = [];

        foreach (self::scanAllConfigFiles($gacelaJson) as $absolutePath) {
            $configs[] = self::readConfigFromFile($gacelaJson, $absolutePath);
        }

        $configs[] = self::loadLocalConfigFile($gacelaJson);

        self::$config = array_merge(...$configs);
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

    public static function hasValue(string $key): bool
    {
        return isset(self::$config[$key]);
    }

    private static function createGacelaJsonConfig(): GacelaJsonConfig
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
    private static function scanAllConfigFiles(GacelaJsonConfig $gacelaJsonConfig): array
    {
        $configGroup = array_map(
            static fn (GacelaJsonConfigItem $config): array => array_map(
                static fn ($p): string => (string)$p,
                array_diff(
                    glob(self::generateAbsolutePath($config->path())),
                    [self::generateAbsolutePath($config->pathLocal())]
                )
            ),
            $gacelaJsonConfig->configs()
        );

        return array_merge(...$configGroup);
    }

    private static function readConfigFromFile(GacelaJsonConfig $gacelaJson, string $absolutePath): array
    {
        $result = [];

        foreach ($gacelaJson->configs() as $config) {
            $result[] = self::readConfigItem($config, $absolutePath);
        }

        return array_merge(...array_filter($result));
    }

    private static function loadLocalConfigFile(GacelaJsonConfig $gacelaJson): array
    {
        $result = [];

        foreach ($gacelaJson->configs() as $config) {
            $configLocalAbsolutePath = self::generateAbsolutePath($config->pathLocal());

            if (is_file($configLocalAbsolutePath)) {
                $result[] = self::readConfigItem($config, $configLocalAbsolutePath);
            }
        }

        return array_merge(...array_filter($result));
    }

    private static function generateAbsolutePath(string $path): string
    {
        return sprintf(
            '%s/%s',
            self::getApplicationRootDir(),
            $path
        );
    }

    private static function readConfigItem(GacelaJsonConfigItem $configItem, string $absolutePath): array
    {
        $reader = ReaderFactory::create($configItem->type());

        if ($reader->canRead($absolutePath)) {
            return $reader->read($absolutePath);
        }

        return [];
    }
}
