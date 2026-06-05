<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Exception\ConfigException;

interface ConfigInterface
{
    public const DEFAULT_CONFIG_VALUE = 'Gacela\Framework\Config::DEFAULT_CONFIG_VALUE';

    /**
     * @throws ConfigException
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = self::DEFAULT_CONFIG_VALUE);

    /**
     * Return the effective merged configuration (all sources combined).
     *
     * @throws ConfigException
     *
     * @return array<string,mixed>
     */
    public function getAllValues(): array;

    public function getSetupGacela(): SetupGacelaInterface;

    public function hasKey(string $key): bool;
}
