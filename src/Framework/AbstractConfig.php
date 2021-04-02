<?php

declare(strict_types=1);

namespace Gacela\Framework;

abstract class AbstractConfig
{
    use ConfigResolverAwareTrait;

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    protected function get(string $key, $default = null)
    {
        return Config::getInstance()->get($key, $default);
    }
}
