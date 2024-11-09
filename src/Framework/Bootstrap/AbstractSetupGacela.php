<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

abstract class AbstractSetupGacela implements SetupGacelaInterface
{
    public const shouldResetInMemoryCache = 'shouldResetInMemoryCache';

    public const fileCacheEnabled = 'fileCacheEnabled';

    public const fileCacheDirectory = 'fileCacheDirectory';

    public const externalServices = 'externalServices';

    public const projectNamespaces = 'projectNamespaces';

    public const configKeyValues = 'configKeyValues';

    public const servicesToExtend = 'servicesToExtend';

    public const plugins = 'plugins';

    public const gacelaConfigsToExtend = 'gacelaConfigsToExtend';

    protected const DEFAULT_ARE_EVENT_LISTENERS_ENABLED = true;

    protected const DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE = false;

    protected const DEFAULT_FILE_CACHE_ENABLED = GacelaFileCache::DEFAULT_ENABLED_VALUE;

    protected const DEFAULT_FILE_CACHE_DIRECTORY = GacelaFileCache::DEFAULT_DIRECTORY_VALUE;

    protected const DEFAULT_PROJECT_NAMESPACES = [];

    protected const DEFAULT_CONFIG_KEY_VALUES = [];

    protected const DEFAULT_GENERIC_LISTENERS = [];

    protected const DEFAULT_SPECIFIC_LISTENERS = [];

    protected const DEFAULT_SERVICES_TO_EXTEND = [];

    protected const DEFAULT_GACELA_CONFIGS_TO_EXTEND = [];

    protected const DEFAULT_PLUGINS = [];

    /**
     * Define different config sources.
     */
    public function buildAppConfig(AppConfigBuilder $builder): AppConfigBuilder
    {
        return $builder;
    }

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string, class-string|object|callable> $externalServices
     */
    public function buildBindings(BindingsBuilder $builder, array $externalServices): BindingsBuilder
    {
        return $builder;
    }

    /**
     * Allow overriding gacela resolvable types.
     */
    public function buildSuffixTypes(SuffixTypesBuilder $builder): SuffixTypesBuilder
    {
        return $builder;
    }

    /**
     * @return array<string, class-string|object|callable>
     */
    public function externalServices(): array
    {
        return [];
    }
}
