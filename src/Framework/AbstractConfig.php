<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Exception\ConfigException;

abstract class AbstractConfig
{
    /**
     * Allow easy access to the root directory of the project.
     */
    public function getAppRootDir(): string
    {
        return Config::getInstance()->getAppRootDir();
    }

    /**
     * Get a project config value by its key.
     *
     * @throws ConfigException
     */
    protected function get(string $key, mixed $default = Config::DEFAULT_CONFIG_VALUE): mixed
    {
        return Config::getInstance()->get($key, $default);
    }

    /**
     * @throws ConfigException
     */
    protected function getString(string $key, ?string $default = null): string
    {
        return Config::getInstance()->getString($key, $default);
    }

    /**
     * @throws ConfigException
     */
    protected function getInt(string $key, ?int $default = null): int
    {
        return Config::getInstance()->getInt($key, $default);
    }

    /**
     * @throws ConfigException
     */
    protected function getFloat(string $key, ?float $default = null): float
    {
        return Config::getInstance()->getFloat($key, $default);
    }

    /**
     * @throws ConfigException
     */
    protected function getBool(string $key, ?bool $default = null): bool
    {
        return Config::getInstance()->getBool($key, $default);
    }

    /**
     * @param array<array-key,mixed>|null $default
     *
     * @throws ConfigException
     *
     * @return array<array-key,mixed>
     */
    protected function getArray(string $key, ?array $default = null): array
    {
        return Config::getInstance()->getArray($key, $default);
    }
}
