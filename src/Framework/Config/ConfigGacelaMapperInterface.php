<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

interface ConfigGacelaMapperInterface
{
    /**
     * @param list<array{path?:string, path_local?:string, reader?:ConfigReaderInterface|class-string}>|array{path?:string, path_local?:string, reader?:ConfigReaderInterface|class-string} $config
     *
     * @return list<GacelaConfigItem>
     */
    public function mapConfigItems(array $config): array;
}
