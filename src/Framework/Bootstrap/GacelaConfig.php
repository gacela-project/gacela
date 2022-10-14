<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\Profiler\GacelaProfiler;
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

    private bool $shouldResetInMemoryCache = GacelaFileCache::DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE_VALUE;

    private bool $fileCacheEnabled = GacelaFileCache::DEFAULT_FILE_CACHE_ENABLED_VALUE;

    private string $fileCacheDirectory = GacelaFileCache::DEFAULT_DIRECTORY_VALUE;

    private bool $profilerEnabled = GacelaProfiler::DEFAULT_ENABLED_VALUE;

    private string $profilerDirectory = GacelaProfiler::DEFAULT_DIRECTORY_VALUE;

    /** @var list<string> */
    private array $projectNamespaces = [];
    /** @var array<string,mixed> */
    private array $configKeyValues = [];

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
     * @return Closure(GacelaConfig):void
     */
    public static function withPhpConfigDefault(): callable
    {
        return static function (self $config): void {
            $config->addAppConfig('config/*.php', 'config/local.php');
        };
    }

    /**
     * @param string $path define the path where Gacela will read all the config files
     * @param string $pathLocal define the path where Gacela will read the local config file
     * @param class-string<ConfigReaderInterface>|ConfigReaderInterface|null $reader Define the reader class which will read and parse the config files
     */
    public function addAppConfig(string $path, string $pathLocal = '', $reader = null): self
    {
        $this->configBuilder->add($path, $pathLocal, $reader);

        return $this;
    }

    public function addSuffixTypeFacade(string $suffix): self
    {
        $this->suffixTypesBuilder->addFacade($suffix);

        return $this;
    }

    public function addSuffixTypeFactory(string $suffix): self
    {
        $this->suffixTypesBuilder->addFactory($suffix);

        return $this;
    }

    public function addSuffixTypeConfig(string $suffix): self
    {
        $this->suffixTypesBuilder->addConfig($suffix);

        return $this;
    }

    public function addSuffixTypeDependencyProvider(string $suffix): self
    {
        $this->suffixTypesBuilder->addDependencyProvider($suffix);

        return $this;
    }

    /**
     * @param class-string $key
     * @param class-string|object|callable $value
     */
    public function addMappingInterface(string $key, $value): self
    {
        $this->mappingInterfacesBuilder->bind($key, $value);

        return $this;
    }

    /**
     * @return class-string|object|callable
     */
    public function getExternalService(string $key)
    {
        return $this->externalServices[$key];
    }

    /**
     * @param class-string|object|callable $value
     */
    public function addExternalService(string $key, $value): self
    {
        $this->externalServices[$key] = $value;

        return $this;
    }

    public function resetInMemoryCache(): self
    {
        $this->shouldResetInMemoryCache = true;

        return $this;
    }

    public function setFileCacheEnabled(bool $flag): self
    {
        $this->fileCacheEnabled = $flag;

        return $this;
    }

    public function setFileCacheDirectory(string $dir): self
    {
        $this->fileCacheDirectory = $dir;

        return $this;
    }

    public function setProfilerEnabled(bool $flag): self
    {
        $this->profilerEnabled = $flag;

        return $this;
    }

    public function setProfilerDirectory(string $dir): self
    {
        $this->profilerDirectory = $dir;

        return $this;
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
     * @param mixed $value
     */
    public function addAppConfigKeyValue(string $key, $value): self
    {
        $this->configKeyValues[$key] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function addAppConfigKeyValues(array $config): self
    {
        $this->configKeyValues = array_merge($this->configKeyValues, $config);

        return $this;
    }

    /**
     * @return array{
     *     external-services: array<string,class-string|object|callable>,
     *     config-builder: ConfigBuilder,
     *     suffix-types-builder: SuffixTypesBuilder,
     *     mapping-interfaces-builder: MappingInterfacesBuilder,
     *     should-reset-in-memory-cache: bool,
     *     file-cache-enabled: bool,
     *     file-cache-directory: string,
     *     profiler-enabled: bool,
     *     profiler-directory: string,
     *     project-namespaces: list<string>,
     *     config-key-values: array<string,mixed>,
     * }
     *
     * @internal
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
            'profiler-enabled' => $this->profilerEnabled,
            'profiler-directory' => $this->profilerDirectory,
            'project-namespaces' => $this->projectNamespaces,
            'config-key-values' => $this->configKeyValues,
        ];
    }
}
