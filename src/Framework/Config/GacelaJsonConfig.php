<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function is_array;

final class GacelaJsonConfig
{
    /** @var array<string,GacelaJsonConfigItem> */
    private array $configs;

    /**
     * @param array<string,GacelaJsonConfigItem> $configs
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
     * @return array<string,GacelaJsonConfigItem>
     */
    private static function getConfigItems(array $json): array
    {
        $first = reset($json['config']);

        if (!is_array($first)) {
            $c = GacelaJsonConfigItem::fromArray($json['config']);
            return [$c->type() => $c];
        }

        $result = [];

        /** @var array<array{type:string,path:string,path_local:string}> $configs */
        $configs = $json['config'];
        foreach ($configs as $config) {
            $c = GacelaJsonConfigItem::fromArray($config);
            $result[$c->type()] = $c;
        }

        return $result;
    }

    public static function withDefaults(): self
    {
        $configItem = GacelaJsonConfigItem::withDefaults();

        return new self([$configItem->type() => $configItem]);
    }

    /**
     * @return array<string,GacelaJsonConfigItem>
     */
    public function configs(): array
    {
        return $this->configs;
    }
}
