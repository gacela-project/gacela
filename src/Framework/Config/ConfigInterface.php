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

    public function getSetupGacela(): SetupGacelaInterface;

    public function hasKey(string $key): bool;
}
