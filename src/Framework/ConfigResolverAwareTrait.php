<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Config\ConfigResolver;

/**
 * @template TConfig of AbstractConfig
 */
trait ConfigResolverAwareTrait
{
    /** @var TConfig|null */
    private ?AbstractConfig $config = null;

    /**
     * @return TConfig
     */
    public function getConfig(): AbstractConfig
    {
        if ($this->config === null) {
            $resolved = (new ConfigResolver())->resolve($this);
            /** @var TConfig $resolved */
            $this->config = $resolved;
        }

        return $this->config;
    }
}
