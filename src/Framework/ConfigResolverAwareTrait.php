<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Config\ConfigNotFoundException;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;

trait ConfigResolverAwareTrait
{
    private ?AbstractConfig $config = null;

    /**
     * Syntax sugar to access the config from static methods.
     */
    public static function config(): AbstractConfig
    {
        return (new static())->getConfig();
    }

    public function getConfig(): AbstractConfig
    {
        if ($this->config === null) {
            $this->config = $this->resolveConfig();
        }

        return $this->config;
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function resolveConfig(): AbstractConfig
    {
        return (new ConfigResolver())->resolve($this);
    }
}
