<?php

declare(strict_types=1);

namespace Gacela\Framework;

use RuntimeException;

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
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function getApplicationRootDir(): string
    {
        return self::$applicationRootDir;
    }

    public static function setApplicationRootDir(string $dir): void
    {
        self::$applicationRootDir = $dir;
    }

    /**
     * @param mixed|null $default
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if (empty(self::$config)) {
            self::init();
        }

        if ($default !== null && !self::hasValue($key)) {
            return $default;
        }

        if (!self::hasValue($key)) {
            throw new RuntimeException(sprintf(
                'Could not find config key "%s" in "%s"',
                $key,
                self::class
            ));
        }

        return self::$config[$key];
    }

    public static function hasValue(string $key): bool
    {
        return isset(self::$config[$key]);
    }

    public static function init(): void
    {
        $configs = [];

        foreach (self::scanAllConfigFiles() as $filename) {
            $configs[] = self::readConfigFromFile($filename);
        }

        $configs[] = self::readConfigFromFile(self::CONFIG_LOCAL_FILENAME);

        self::$config = array_merge(...$configs);
    }

    private static function scanAllConfigFiles(): array
    {
        return array_diff(
            scandir(self::$applicationRootDir . '/config/'),
            ['..', '.', self::CONFIG_LOCAL_FILENAME]
        );
    }

    private static function readConfigFromFile(string $type): array
    {
        $fileName = self::$applicationRootDir . '/config/' . $type;

        if (file_exists($fileName)) {
            return include $fileName;
        }

        return [];
    }
}
