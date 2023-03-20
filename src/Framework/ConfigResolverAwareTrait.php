<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Config\ConfigResolver;

trait ConfigResolverAwareTrait
{
    private ?AbstractConfig $config = null;

    public function getConfig(): AbstractConfig
    {
        if ($this->config === null) {
            $this->config = (new ConfigResolver())->resolve($this);
        }

        return $this->config;
    }
}
