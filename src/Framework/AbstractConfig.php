<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Exception\ConfigException;

abstract class AbstractConfig
{
    /**
     * Allow easy access to the root dir of the project.
     */
    public function getAppRootDir(): string
    {
        return Config::getInstance()->getAppRootDir();
    }
    /**
     * @param null|mixed $default
     *
     * @throws ConfigException
     *
     * @return mixed
     */
    protected function get(string $key, $default = Config::DEFAULT_CONFIG_VALUE)
    {
        return Config::getInstance()->get($key, $default);
    }
}
