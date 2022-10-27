<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

final class GacelaConfig
{
    private ConfigBuilder $configBuilder;

    private SuffixTypesBuilder $suffixTypesBuilder;

    private MappingInterfacesBuilder $mappingInterfacesBuilder;

    /** @var array<string, class-string|object|callable> */
    private array $externalServices;

    private bool $shouldResetInMemoryCache = false;

    private bool $fileCacheEnabled = GacelaFileCache::DEFAULT_ENABLED_VALUE;

    private string $fileCacheDirectory = GacelaFileCache::DEFAULT_DIRECTORY_VALUE;

    /** @var list<string> */
    private array $projectNamespaces = [];
    /** @var array<string,mixed> */
    private array $configKeyValues = [];

    /** @var array<string,list<callable>> */
    private array $listeners = [];

    /**
     * @param array<string,class-string|object|callable> $externalServices
     */
    public function __construct(array $externalServices = [])
    {
        $this->externalServices = $externalServices;
        $this->configBuilder = new ConfigBuilder();
        $this->suffixTypesBuilder = new SuffixTypesBuilder();
        $this->mappingInterfacesBuilder = new MappingInterfacesBuilder();
    }

    /**
     * Define 'config/*.php' as path, and 'config/local.php' as local path for the configuration.
     *
     * @return Closure(GacelaConfig):void
     */
    public static function withPhpConfigDefault(): callable
    {
        return static function (self $config): void {
            $config->addAppConfig('config/*.php', 'config/local.php');
        };
    }

    /**
     * Define the path where the configuration will be stored.
     *
     * @param string $path define the path where Gacela will read all the config files
     * @param string $pathLocal define the path where Gacela will read the local config file
     * @param class-string<ConfigReaderInterface>|ConfigReaderInterface|null $reader Define the reader class which will read and parse the config files
     */
    public function addAppConfig(string $path, string $pathLocal = '', $reader = null): self
    {
        $this->configBuilder->add($path, $pathLocal, $reader);

        return $this;
    }

    /**
     * Allow overriding gacela facade suffixes.
     */
    public function addSuffixTypeFacade(string $suffix): self
    {
        $this->suffixTypesBuilder->addFacade($suffix);

        return $this;
    }

    /**
     * Allow overriding gacela factory suffixes.
     */
    public function addSuffixTypeFactory(string $suffix): self
    {
        $this->suffixTypesBuilder->addFactory($suffix);

        return $this;
    }

    /**
     * Allow overriding gacela config suffixes.
     */
    public function addSuffixTypeConfig(string $suffix): self
    {
        $this->suffixTypesBuilder->addConfig($suffix);

        return $this;
    }

    /**
     * Allow overriding gacela dependency provider suffixes.
     */
    public function addSuffixTypeDependencyProvider(string $suffix): self
    {
        $this->suffixTypesBuilder->addDependencyProvider($suffix);

        return $this;
    }

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param class-string $key
     * @param class-string|object|callable $value
     */
    public function addMappingInterface(string $key, $value): self
    {
        $this->mappingInterfacesBuilder->bind($key, $value);

        return $this;
    }

    /**
     * Useful to pass services while bootstrapping Gacela to the gacela.php config file.
     *
     * @param class-string|object|callable $value
     */
    public function addExternalService(string $key, $value): self
    {
        $this->externalServices[$key] = $value;

        return $this;
    }

    /**
     * Get an external service from its defined key, previously added using `addExternalService()`.
     *
     * @return class-string|object|callable
     */
    public function getExternalService(string $key)
    {
        return $this->externalServices[$key];
    }

    /**
     * Enable resetting the memory cache on each setup. Useful for functional tests.
     */
    public function resetInMemoryCache(): self
    {
        $this->shouldResetInMemoryCache = true;

        return $this;
    }

    /**
     * Define whether the file cache flag is enabled.
     */
    public function setFileCacheEnabled(bool $flag): self
    {
        $this->fileCacheEnabled = $flag;

        return $this;
    }

    /**
     * Define the file cache directory.
     */
    public function setFileCacheDirectory(string $dir): self
    {
        $this->fileCacheDirectory = $dir;

        return $this;
    }

    /**
     * Define a list of project namespaces.
     *
     * @param list<string> $list
     */
    public function setProjectNamespaces(array $list): self
    {
        $this->projectNamespaces = $list;

        return $this;
    }

    /**
     * Add/replace an existent configuration key with a specific value.
     *
     * @param mixed $value
     */
    public function addAppConfigKeyValue(string $key, $value): self
    {
        $this->configKeyValues[$key] = $value;

        return $this;
    }

    /**
     * Add/replace a list of existent configuration keys with a specific value.
     *
     * @param array<string, mixed> $config
     */
    public function addAppConfigKeyValues(array $config): self
    {
        $this->configKeyValues = array_merge($this->configKeyValues, $config);

        return $this;
    }

    public function addEventListener(string $listener, callable $callable): self
    {
        $this->listeners[$listener][] = $callable;

        return $this;
    }

    /**
     * @internal
     *
     * @return array{
     *     external-services: array<string,class-string|object|callable>,
     *     config-builder: ConfigBuilder,
     *     suffix-types-builder: SuffixTypesBuilder,
     *     mapping-interfaces-builder: MappingInterfacesBuilder,
     *     should-reset-in-memory-cache: bool,
     *     file-cache-enabled: bool,
     *     file-cache-directory: string,
     *     project-namespaces: list<string>,
     *     config-key-values: array<string,mixed>,
     *     listeners: array<string,list<callable>>,
     * }
     */
    public function build(): array
    {
        return [
            'external-services' => $this->externalServices,
            'config-builder' => $this->configBuilder,
            'suffix-types-builder' => $this->suffixTypesBuilder,
            'mapping-interfaces-builder' => $this->mappingInterfacesBuilder,
            'should-reset-in-memory-cache' => $this->shouldResetInMemoryCache,
            'file-cache-enabled' => $this->fileCacheEnabled,
            'file-cache-directory' => $this->fileCacheDirectory,
            'project-namespaces' => $this->projectNamespaces,
            'config-key-values' => $this->configKeyValues,
            'listeners' => $this->listeners,
        ];
    }
}
