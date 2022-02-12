<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

final class ConfigGacelaMapper
{
    /**
     * @param array<array>|array{path:string,path_local:string} $config
     *
     * @return list<GacelaConfigItem>
     */
    public function mapConfigItems(array $config): array
    {
        if ($this->isSingleConfigFile($config)) {
            return [GacelaConfigItem::fromArray($config)];
        }

        $result = [];

        /** @var array<array{path:string,path_local:string}> $config */
        foreach ($config as $configItem) {
            $c = GacelaConfigItem::fromArray($configItem);
            $result[] = $c;
        }

        return $result;
    }

    /**
     * @param array<array>|array{path:string,path_local:string} $config
     */
    private function isSingleConfigFile(array $config): bool
    {
        return isset($config['path'])
            || isset($config['path_local']);
    }
}
