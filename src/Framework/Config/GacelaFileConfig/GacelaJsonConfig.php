<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use function is_array;

/**
 * @psalm-suppress DeprecatedClass
 *
 * @deprecated
 */
final class GacelaJsonConfig implements GacelaFileConfig
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
     * @param array{config: array<array>|array{type:string,path:string,path_local:string}} $json
     */
    public static function fromArray(array $json): self
    {
        return new self(
            self::getConfigItems($json)
        );
    }

    /**
     * @param array{config: array<array>|array{type:string,path:string,path_local:string}} $json
     *
     * @return array<string,GacelaFileConfigItem>
     */
    private static function getConfigItems(array $json): array
    {
        $first = reset($json['config']);

        if (!is_array($first)) {
            /** @psalm-suppress DeprecatedClass */
            $c = GacelaJsonConfigItem::fromArray($json['config']);
            return [$c->type() => $c];
        }

        $result = [];

        /** @var array<array{type:string,path:string,path_local:string}> $configs */
        $configs = $json['config'];
        foreach ($configs as $config) {
            /** @psalm-suppress DeprecatedClass */
            $c = GacelaJsonConfigItem::fromArray($config);
            $result[$c->type()] = $c;
        }

        return $result;
    }

    public static function withDefaults(): self
    {
        /** @psalm-suppress DeprecatedClass */
        $configItem = GacelaJsonConfigItem::withDefaults();

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
