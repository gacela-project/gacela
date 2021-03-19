<?php

declare(strict_types=1);

namespace Gacela;

use ArrayObject;
use RuntimeException;

final class Config
{
    public const CONFIG_FILE_PREFIX = '/config.php';

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
     * @param mixed|null $default
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public static function get(string $key, $default = null)
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
        $fileName = static::$applicationRootDir . static::CONFIG_FILE_PREFIX;

        if (file_exists($fileName)) {
            include $fileName;
        }

        static::$config = $config;
    }
}
