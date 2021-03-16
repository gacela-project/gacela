<?php

declare(strict_types=1);

namespace Gacela;

abstract class AbstractConfig
{
    use ConfigResolverAwareTrait;

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    protected function get($key, $default = null)
    {
        return $this->getConfig()->get($key, $default);
    }

    protected function getConfig(): Config
    {
        return Config::getInstance();
    }
}
