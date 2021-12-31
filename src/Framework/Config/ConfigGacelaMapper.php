<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

final class ConfigGacelaMapper
{
    /**
     * @param array<array>|array{type:string,path:string,path_local:string} $config
     *
     * @return array<string,GacelaConfigItem>
     */
    public function mapConfigItems(array $config): array
    {
        if ($this->isSingleConfigFile($config)) {
            /** @var array $config */
            $c = GacelaConfigItem::fromArray($config);
            return [$c->type() => $c];
        }

        $result = [];

        /** @var array<array{type:string,path:string,path_local:string}> $config */
        foreach ($config as $configItem) {
            $c = GacelaConfigItem::fromArray($configItem);
            $result[$c->type()] = $c;
        }

        return $result;
    }

    /**
     * @param array<array>|array{type:string,path:string,path_local:string} $config
     */
    private function isSingleConfigFile(array $config): bool
    {
        return isset($config['type'])
            || isset($config['path'])
            || isset($config['path_local']);
    }
}
