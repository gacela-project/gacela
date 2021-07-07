<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\GacelaJsonConfig;
use Gacela\Framework\Config\ReaderFactory;
use Gacela\Framework\Exception\ConfigException;

final class Config
{
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

        foreach (self::scanAllConfigFiles($gacelaJson) as $fullPath) {
            $configs[] = self::readConfigFromFile($gacelaJson, $fullPath);
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

    /**
     * @throws ConfigException
     *
     * @return string[]
     */
    private static function scanAllConfigFiles(GacelaJsonConfig $gacelaJsonConfig): array
    {
        $rootDir = self::getApplicationRootDir();
        $configGroup = [];

        foreach ($gacelaJsonConfig->configs() as $config) {
            $configDir = sprintf('%s/%s', $rootDir, $config->path());

            $filteredPaths = array_diff(
                glob($configDir),
                [sprintf('%s/%s', $rootDir, $config->pathLocal())]
            );

            $configGroup[] = array_map(static fn ($p) => (string)$p, $filteredPaths);
        }

        return array_merge(...$configGroup);
    }

    private static function readConfigFromFile(GacelaJsonConfig $gacelaJson, string $file): array
    {
        $result = [];
        foreach ($gacelaJson->configs() as $config) {
            $reader = ReaderFactory::create($config->type());
            if ($reader->canRead($file)) {
                $result[] = $reader->read($file);
            }
        }

        return array_merge(...array_filter($result));
    }

    private static function createGacelaJsonConfig(): GacelaJsonConfig
    {
        $gacelaJsonPath = self::getApplicationRootDir() . '/gacela.json';

        if (is_file($gacelaJsonPath)) {
            /** @psalm-suppress MixedArgumentTypeCoercion */
            return GacelaJsonConfig::fromArray(
                (array)json_decode(file_get_contents($gacelaJsonPath), true)
            );
        }

        return GacelaJsonConfig::withDefaults();
    }

    private static function loadLocalConfigFile(GacelaJsonConfig $gacelaJson): array
    {
        $result = [];

        foreach ($gacelaJson->configs() as $config) {
            $configLocalAbsolutePath = sprintf(
                '%s/%s',
                self::getApplicationRootDir(),
                $config->pathLocal()
            );

            if (is_file($configLocalAbsolutePath)) {
                $reader = ReaderFactory::create($config->type());
                if ($reader->canRead($configLocalAbsolutePath)) {
                    $result[] = $reader->read($configLocalAbsolutePath);
                }
            }
        }

        return array_merge(...array_filter($result));
    }
}
