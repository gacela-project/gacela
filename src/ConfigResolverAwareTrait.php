<?php

declare(strict_types=1);

namespace Gacela;

use Gacela\ClassResolver\Config\ConfigResolver;

trait ConfigResolverAwareTrait
{
    private ?AbstractConfig $config = null;

    public function setConfig(AbstractConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    protected function getConfig(): AbstractConfig
    {
        if ($this->config === null) {
            $this->config = $this->resolveConfig();
        }

        return $this->config;
    }

    private function resolveConfig(): AbstractConfig
    {
        $resolver = new ConfigResolver();
        return $resolver->resolve($this);
    }
}
