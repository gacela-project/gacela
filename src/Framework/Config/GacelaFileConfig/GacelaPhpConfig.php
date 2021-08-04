<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

final class GacelaPhpConfig implements GacelaFileConfig
{
    /** @var array<string,GacelaFileConfigItem> */
    private array $configs;

    /**
     * @param array<string,GacelaFileConfigItem> $configs
     */
    private function __construct(array $configs)
    {
        $this->configs = $configs;
    }

    /**
     * @param array{config: array<array>|array{type:string,path:string,path_local:string}} $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            self::getConfigItems($config)
        );
    }

    /**
     * @param array{config: array<array>|array{type:string,path:string,path_local:string}} $gacelaConfig
     *
     * @return array<string,GacelaFileConfigItem>
     */
    private static function getConfigItems(array $gacelaConfig): array
    {
        $configuration = $gacelaConfig['config'];

        if (!self::isMultiConfigFile($configuration)) {
            $c = GacelaPhpConfigItem::fromArray($gacelaConfig['config']);
            return [$c->type() => $c];
        }

        $result = [];

        /** @var array<array{type:string,path:string,path_local:string}> $configs */
        $configs = $gacelaConfig['config'];
        foreach ($configs as $config) {
            $c = GacelaPhpConfigItem::fromArray($config);
            $result[$c->type()] = $c;
        }

        return $result;
    }

    private static function isMultiConfigFile(array $config): bool
    {
        return count(array_filter($config, 'is_array')) >= 1;
    }

    public static function withDefaults(): self
    {
        $configItem = GacelaPhpConfigItem::withDefaults();

        return new self([$configItem->type() => $configItem]);
    }

    /**
     * @return array<string,GacelaFileConfigItem>
     */
    public function configs(): array
    {
        return $this->configs;
    }
}
