<?php

declare(strict_types=1);

namespace Gacela\Framework;

/**
 * Interface for accessing module configuration.
 *
 * @template TConfig of AbstractConfig
 */
interface ConfigAccessorInterface
{
    /**
     * Get the configuration instance for this module.
     *
     * @return TConfig
     */
    public function getConfig(): AbstractConfig;
}
