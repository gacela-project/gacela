<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

final class GacelaConfigFile
{
    /** @var array<string,GacelaConfigItem> */
    private array $configs;

    /** @var array<string,string|callable> */
    private array $mappingInterfaces;

    /**
     * @param array<string,GacelaConfigItem> $configs
     * @param array<string,string|callable>  $mappingInterfaces
     */
    private function __construct(
        array $configs,
        array $mappingInterfaces
    ) {
        $this->configs = $configs;
        $this->mappingInterfaces = $mappingInterfaces;
    }

    /**
     * @param array{
     *     config: array<array>|array{type:string,path:string,path_local:string},
     *     mapping-interfaces: array<string,string|callable>,
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            self::getConfigItems($array['config'] ?? []),
            $array['mapping-interfaces'] ?? []
        );
    }

    /**
     * @param array<array>|array{type:string,path:string,path_local:string} $config
     *
     * @return array<string,GacelaConfigItem>
     */
    private static function getConfigItems(array $config): array
    {
        if (self::isSingleConfigFile($config)) {
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

    private static function isSingleConfigFile(array $config): bool
    {
        return isset($config['type'])
            || isset($config['path'])
            || isset($config['path_local']);
    }

    public static function withDefaults(): self
    {
        $configItem = GacelaConfigItem::withDefaults();

        return new self(
            [$configItem->type() => $configItem],
            []
        );
    }

    /**
     * @return array<string,GacelaConfigItem>
     */
    public function getConfigs(): array
    {
        return $this->configs;
    }

    /**
     * Map interfaces to concrete classes or callable (which will be resolved on runtime).
     * This is util to inject dependencies to Gacela services (such as Factories, for example) via their constructor.
     *
     * @return mixed
     */
    public function getMappingInterface(string $key)
    {
        return $this->mappingInterfaces[$key] ?? null;
    }
}
