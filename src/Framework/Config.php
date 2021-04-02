<?php

declare(strict_types=1);

namespace Gacela\Framework;

use RuntimeException;

final class Config
{
    public const CONFIG_FILE_SUFFIX = '.php';

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
        self::$config = array_merge(
            self::populateConfig('/config'),
            self::populateConfig('/config_local'),
        );
    }

    private static function populateConfig(string $type): array
    {
        $fileName = self::$applicationRootDir . $type . self::CONFIG_FILE_SUFFIX;

        if (file_exists($fileName)) {
            return include $fileName;
        }

        return [];
    }
}
