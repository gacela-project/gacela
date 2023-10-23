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
}
