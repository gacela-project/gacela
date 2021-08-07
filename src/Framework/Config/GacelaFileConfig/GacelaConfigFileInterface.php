<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

interface GacelaConfigFileInterface
{
    /**
     * @return array<string,GacelaConfigItemInterface>
     */
    public function getConfigs(): array;

    /**
     * Map interfaces to concrete classes or callable (which will be resolved on runtime).
     * This is util to inject dependencies to Gacela services (such as Factories, for example) via their constructor.
     *
     * @return array<string,string|callable>
     */
    public function getInterfacesMapping(): array;
}
