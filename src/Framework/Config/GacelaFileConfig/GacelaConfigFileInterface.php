<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

interface GacelaConfigFileInterface
{
    /**
     * @return array<string,GacelaConfigItemInterface>
     */
    public function configs(): array;

    /**
     * @return array<string,string|callable>
     */
    public function dependencies(): array;

    /**
     * @return list<string>
     */
    public function autoloadDependencies(): array;
}
