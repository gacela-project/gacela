<?php

declare(strict_types=1);

namespace Gacela;

use ArrayObject;
use RuntimeException;

final class Config
{
    public const CONFIG_FILE_PREFIX = '/config.php';

    private static string $applicationRootDir = '';
    private static ?ArrayObject $config = null;
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

        /** @psalm-suppress PossiblyNullReference, PossiblyNullArrayAccess */
        return self::$config[$key];
    }

    public static function hasValue(string $key): bool
    {
        return isset(self::$config[$key]);
    }

    public static function init(): void
    {
        $config = new ArrayObject();
        $fileName = self::$applicationRootDir . self::CONFIG_FILE_PREFIX;

        if (file_exists($fileName)) {
            include $fileName;
        }

        self::$config = $config;
    }
}
