<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

final class GacelaPhpConfigFile implements GacelaConfigFileInterface
{
    /** @var array<string,GacelaConfigItemInterface> */
    private array $configs;

    /** @var array<string,string|callable> */
    private array $interfacesMapping;

    /**
     * @param array<string,GacelaConfigItemInterface> $configs
     * @param array<string,string|callable> $interfacesMapping
     */
    private function __construct(
        array $configs,
        array $interfacesMapping
    ) {
        $this->configs = $configs;
        $this->interfacesMapping = $interfacesMapping;
    }

    /**
     * @param array{
     *     config: array<array>|array{type:string,path:string,path_local:string},
     *     interfaces-mapping: array<string,string|callable>,
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            self::getConfigItems($array['config'] ?? []),
            $array['interfaces-mapping'] ?? []
        );
    }

    /**
     * @param array<array>|array{type:string,path:string,path_local:string} $config
     *
     * @return array<string,GacelaConfigItemInterface>
     */
    private static function getConfigItems(array $config): array
    {
        if (self::isSingleConfigFile($config)) {
            $c = GacelaPhpConfigItem::fromArray($config);
            return [$c->type() => $c];
        }

        $result = [];

        /** @var array<array{type:string,path:string,path_local:string}> $config */
        foreach ($config as $configItem) {
            $c = GacelaPhpConfigItem::fromArray($configItem);
            $result[$c->type()] = $c;
        }

        return $result;
    }

    private static function isSingleConfigFile(array $config): bool
    {
        return isset($config['type'])
            || isset($config['path'])
            || isset($config['path_local']);
    }

    public static function withDefaults(): self
    {
        $configItem = GacelaPhpConfigItem::withDefaults();

        return new self(
            [$configItem->type() => $configItem],
            []
        );
    }

    /**
     * @return array<string,GacelaConfigItemInterface>
     */
    public function getConfigs(): array
    {
        return $this->configs;
    }

    /**
     * @return array<string,string|callable>
     */
    public function getInterfacesMapping(): array
    {
        return $this->interfacesMapping;
    }
}
