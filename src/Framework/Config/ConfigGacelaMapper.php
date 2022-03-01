<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

final class ConfigGacelaMapper
{
    /**
     * @param list<array{path?:string, path_local?:string, reader?:ConfigReaderInterface|class-string}>|array{path?:string, path_local?:string, reader?:ConfigReaderInterface|class-string} $config
     *
     * @return list<GacelaConfigItem>
     */
    public function mapConfigItems(array $config): array
    {
        if (isset($config['path']) || isset($config['path_local'])) {
            /** @psalm-suppress InvalidArgument */
            return [GacelaConfigItem::fromArray($config)]; // @phpstan-ignore-line
        }

        $result = [];

        /** @var list<array{path?:string, path_local?:string, reader?:ConfigReaderInterface|class-string}> $config */
        foreach ($config as $configItem) {
            $c = GacelaConfigItem::fromArray($configItem);
            $result[] = $c;
        }

        return $result;
    }
}
