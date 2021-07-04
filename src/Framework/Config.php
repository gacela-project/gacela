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

    private static GacelaJsonConfig $gacelaJson;

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
        self::$gacelaJson = self::createGacelaJsonConfig();
        $configs = [];

        foreach (self::scanAllConfigFiles() as $fullPath) {
            if (self::isValidConfigExtensionFile($fullPath)) {
                $configs[] = self::readConfigFromFile($fullPath);
            }
        }

        $configs[] = self::readConfigFromFile(self::configLocalAbsolutePath());

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
    private static function scanAllConfigFiles(): array
    {
        $rootDir = self::getApplicationRootDir();
        $configDir = sprintf('%s/%s', $rootDir, self::$gacelaJson->path());

        $filteredPaths = array_diff(
            glob($configDir),
            [sprintf('%s/%s', $rootDir, self::$gacelaJson->pathLocal())]
        );

        return array_map(static fn ($p) => (string)$p, $filteredPaths);
    }

    private static function configLocalAbsolutePath(): string
    {
        return sprintf(
            '%s/%s',
            self::getApplicationRootDir(),
            self::$gacelaJson->pathLocal()
        );
    }

    private static function isValidConfigExtensionFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $extension = (false !== strpos($path, '.env'))
            ? 'env'
            : pathinfo($path, PATHINFO_EXTENSION);

        return self::$gacelaJson->type() === $extension;
    }

    private static function readConfigFromFile(string $file): array
    {
        return ReaderFactory::create(self::$gacelaJson->type())->read($file);
    }

    private static function createGacelaJsonConfig(): GacelaJsonConfig
    {
        $gacelaJsonPath = self::getApplicationRootDir() . '/gacela.json';

        if (is_file($gacelaJsonPath)) {
            return GacelaJsonConfig::fromArray(
                (array)json_decode(file_get_contents($gacelaJsonPath), true)
            );
        }

        return GacelaJsonConfig::withDefaults();
    }
}
