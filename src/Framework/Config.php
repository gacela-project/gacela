<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Exception\ConfigException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

final class Config
{
    /**
     * This file config/local.php could be ignore in your project, and it will be read the last one
     * so it will override every possible value.
     */
    private const CONFIG_LOCAL_FILENAME = 'local.php';

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
        $configs = [];

        foreach (self::scanAllConfigFiles() as $filename) {
            $fileNameOrDir = self::fullPath($filename);

            if (is_dir($fileNameOrDir)) {
                /** @var array{0:string} $fileInfo */
                foreach (self::createRecursiveIterator($fileNameOrDir) as $fileInfo) {
                    if (self::isPhpFile($fileInfo[0])) {
                        $configs[] = self::readConfigFromFile($fileInfo[0]);
                    }
                }
            } elseif (self::isPhpFile($fileNameOrDir)) {
                $configs[] = self::readConfigFromFile($fileNameOrDir);
            }
        }

        $configs[] = self::readConfigFromFile(self::fullPath(self::CONFIG_LOCAL_FILENAME));

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
        $configDir = self::getApplicationRootDir() . '/config/';

        if (!is_dir($configDir)) {
            throw ConfigException::configDirNotFound(self::getApplicationRootDir());
        }

        $paths = array_diff(
            scandir($configDir),
            ['..', '.', self::CONFIG_LOCAL_FILENAME]
        );

        return array_map(static fn ($p) => (string) $p, $paths);
    }

    private static function fullPath(string $fileNameOrDir): string
    {
        return self::getApplicationRootDir() . '/config/' . $fileNameOrDir;
    }

    private static function createRecursiveIterator(string $path): RegexIterator
    {
        return new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)),
            '/^.+\.php$/i',
            RecursiveRegexIterator::GET_MATCH
        );
    }

    private static function isPhpFile(string $path): bool
    {
        return is_file($path) && 'php' === pathinfo($path, PATHINFO_EXTENSION);
    }

    private static function readConfigFromFile(string $file): array
    {
        if (file_exists($file)) {
            /** @var null|array $content */
            $content = include $file;

            return is_array($content) ? $content : [];
        }

        return [];
    }
}
