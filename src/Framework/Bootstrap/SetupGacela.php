<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\ClassResolver\Cache\GacelaCache;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

final class SetupGacela extends AbstractSetupGacela
{
    /** @var callable(ConfigBuilder):void */
    private $configFn;

    /** @var callable(MappingInterfacesBuilder,array<string,mixed>):void */
    private $mappingInterfacesFn;

    /** @var callable(SuffixTypesBuilder):void */
    private $suffixTypesFn;

    /** @var array<string,class-string|object|callable> */
    private array $externalServices = [];

    private ?ConfigBuilder $configBuilder = null;

    private ?SuffixTypesBuilder $suffixTypesBuilder = null;

    private ?MappingInterfacesBuilder $mappingInterfacesBuilder = null;

    private bool $cacheEnabled = true;

    private string $cacheDirectory = GacelaCache::DEFAULT_DIRECTORY_VALUE;

    /** @var list<string> */
    private array $projectNamespaces = [];

    /** @var array<string,mixed> */
    private array $configKeyValues = [];

    public function __construct()
    {
        $this->configFn = static function (): void {
        };
        $this->mappingInterfacesFn = static function (): void {
        };
        $this->suffixTypesFn = static function (): void {
        };
    }

    /**
     * @param Closure(GacelaConfig):void $setupGacelaFileFn
     */
    public static function fromCallable(Closure $setupGacelaFileFn): self
    {
        $gacelaConfig = new GacelaConfig();
        $setupGacelaFileFn($gacelaConfig);

        return self::fromGacelaConfig($gacelaConfig);
    }

    public static function fromGacelaConfig(GacelaConfig $gacelaConfig): self
    {
        $build = $gacelaConfig->build();

        return (new self())
            ->setConfigBuilder($build['config-builder'])
            ->setSuffixTypesBuilder($build['suffix-types-builder'])
            ->setMappingInterfacesBuilder($build['mapping-interfaces-builder'])
            ->setExternalServices($build['external-services'])
            ->setCacheEnabled($build['cache-enabled'])
            ->setCacheDirectory($build['cache-directory'])
            ->setProjectNamespaces($build['project-namespaces'])
            ->setConfigKeyValues($build['config-key-values']);
    }

    public function setMappingInterfacesBuilder(MappingInterfacesBuilder $builder): self
    {
        $this->mappingInterfacesBuilder = $builder;

        return $this;
    }

    public function setSuffixTypesBuilder(SuffixTypesBuilder $builder): self
    {
        $this->suffixTypesBuilder = $builder;

        return $this;
    }

    public function setConfigBuilder(ConfigBuilder $builder): self
    {
        $this->configBuilder = $builder;

        return $this;
    }

    /**
     * @param callable(ConfigBuilder):void $callable
     */
    public function setConfigFn(callable $callable): self
    {
        $this->configFn = $callable;

        return $this;
    }

    public function buildConfig(ConfigBuilder $configBuilder): ConfigBuilder
    {
        if ($this->configBuilder) {
            $configBuilder = $this->configBuilder;
        }

        ($this->configFn)($configBuilder);

        return $configBuilder;
    }

    /**
     * @param callable(MappingInterfacesBuilder,array<string,mixed>):void $callable
     */
    public function setMappingInterfacesFn(callable $callable): self
    {
        $this->mappingInterfacesFn = $callable;

        return $this;
    }

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string,class-string|object|callable> $externalServices
     */
    public function buildMappingInterfaces(
        MappingInterfacesBuilder $mappingInterfacesBuilder,
        array $externalServices
    ): MappingInterfacesBuilder {
        if ($this->mappingInterfacesBuilder) {
            $mappingInterfacesBuilder = $this->mappingInterfacesBuilder;
        }

        ($this->mappingInterfacesFn)(
            $mappingInterfacesBuilder,
            array_merge($this->externalServices, $externalServices)
        );

        return $mappingInterfacesBuilder;
    }

    /**
     * @param callable(SuffixTypesBuilder):void $callable
     */
    public function setSuffixTypesFn(callable $callable): self
    {
        $this->suffixTypesFn = $callable;

        return $this;
    }

    /**
     * Allow overriding gacela resolvable types.
     */
    public function buildSuffixTypes(SuffixTypesBuilder $suffixTypesBuilder): SuffixTypesBuilder
    {
        if ($this->suffixTypesBuilder) {
            $suffixTypesBuilder = $this->suffixTypesBuilder;
        }

        ($this->suffixTypesFn)($suffixTypesBuilder);

        return $suffixTypesBuilder;
    }

    /**
     * @param array<string,class-string|object|callable> $array
     */
    public function setExternalServices(array $array): self
    {
        $this->externalServices = $array;

        return $this;
    }

    /**
     * @return array<string,class-string|object|callable>
     */
    public function externalServices(): array
    {
        return $this->externalServices;
    }

    public function setCacheEnabled(bool $flag): self
    {
        $this->cacheEnabled = $flag;

        return $this;
    }

    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    public function setCacheDirectory(string $dir): self
    {
        $this->cacheDirectory = $dir;

        return $this;
    }

    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    /**
     * @param list<string> $list
     */
    public function setProjectNamespaces(array $list): self
    {
        $this->projectNamespaces = $list;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getProjectNamespaces(): array
    {
        return $this->projectNamespaces;
    }

    /**
     * @return array<string,mixed>
     */
    public function getConfigKeyValues(): array
    {
        return $this->configKeyValues;
    }

    /**
     * @param array<string,mixed> $configKeyValues
     */
    private function setConfigKeyValues(array $configKeyValues): self
    {
        $this->configKeyValues = $configKeyValues;

        return $this;
    }
}
