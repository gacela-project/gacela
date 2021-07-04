<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Config\ConfigNotFoundException;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;

trait ConfigResolverAwareTrait
{
    private ?AbstractConfig $config = null;

    protected function getConfig(): AbstractConfig
    {
        if (null === $this->config) {
            $this->config = $this->resolveConfig();
        }

        return $this->config;
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function resolveConfig(): AbstractConfig
    {
        $resolver = new ConfigResolver();

        return $resolver->resolve($this);
    }
}
