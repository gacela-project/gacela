<?php

declare(strict_types=1);

namespace Gacela;

use ArrayObject;
use RuntimeException;

final class Config
{
    public const CONFIG_FILE_PREFIX = '/config/config_';
    public const CONFIG_FILE_SUFFIX = '.php';

    public static string $applicationRootDir = '';

    private static ?ArrayObject $config = null;
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        if (empty(static::$config)) {
            static::init();
        }

        if (!static::hasValue($key) && $default !== null) {
            return $default;
        }

        if (!static::hasValue($key)) {
            throw new RuntimeException(sprintf(
                'Could not find config key "%s" in "%s"',
                $key,
                self::class
            ));
        }

        /** @psalm-suppress PossiblyNullReference, PossiblyNullArrayAccess */
        return static::$config[$key];
    }

    public static function hasValue(string $key): bool
    {
        return isset(static::$config[$key]);
    }

    public static function init(): void
    {
        $config = new ArrayObject();
        // config_default.php
        static::buildConfig('default', $config);

        static::$config = $config;
    }

    private static function buildConfig(string $type, ArrayObject $config): ArrayObject
    {
        $fileName = static::$applicationRootDir . static::CONFIG_FILE_PREFIX . $type . static::CONFIG_FILE_SUFFIX;
        if (file_exists($fileName)) {
            include $fileName;
        }

        return $config;
    }
}
