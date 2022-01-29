<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Exception\ConfigException;

abstract class AbstractConfig
{
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

    protected function getAppRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }
}
