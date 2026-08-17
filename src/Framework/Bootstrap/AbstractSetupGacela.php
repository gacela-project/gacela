<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\Schema\ConfigType;

/**
 * @psalm-import-type ExternalServicesMap from BuilderConfigurationInterface
 */
abstract class AbstractSetupGacela implements SetupGacelaInterface
{
    public const string shouldResetInMemoryCache = 'shouldResetInMemoryCache';

    public const string fileCacheEnabled = 'fileCacheEnabled';

    public const string fileCacheDirectory = 'fileCacheDirectory';

    public const string externalServices = 'externalServices';

    public const string projectNamespaces = 'projectNamespaces';

    public const string configDimensions = 'configDimensions';

    public const string appModulePaths = 'appModulePaths';

    public const string dontDiscover = 'dontDiscover';

    public const string configKeyValues = 'configKeyValues';

    public const string configSchema = 'configSchema';

    public const string dtoSchema = 'dtoSchema';

    public const string stubsDir = 'stubsDir';

    public const string servicesToExtend = 'servicesToExtend';

    public const string providerServicesToExtend = 'providerServicesToExtend';

    public const string factories = 'factories';

    public const string protectedServices = 'protectedServices';

    public const string aliases = 'aliases';

    public const string contextualBindings = 'contextualBindings';

    public const string handlerRegistries = 'handlerRegistries';

    public const string pluginStacks = 'pluginStacks';

    public const string tags = 'tags';

    public const string afterResolvingCallbacks = 'afterResolvingCallbacks';

    public const string lazyServices = 'lazyServices';

    public const string definitions = 'definitions';

    public const string plugins = 'plugins';

    public const string gacelaConfigsToExtend = 'gacelaConfigsToExtend';

    protected const bool DEFAULT_ARE_EVENT_LISTENERS_ENABLED = true;

    protected const bool DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE = false;

    protected const bool DEFAULT_FILE_CACHE_ENABLED = GacelaFileCache::DEFAULT_ENABLED_VALUE;

    protected const ?string DEFAULT_FILE_CACHE_DIRECTORY = GacelaFileCache::DEFAULT_DIRECTORY_VALUE;

    protected const array DEFAULT_PROJECT_NAMESPACES = [];

    protected const array DEFAULT_CONFIG_DIMENSIONS = [];

    protected const array DEFAULT_APP_MODULE_PATHS = [];

    protected const array DEFAULT_DONT_DISCOVER = [];

    protected const array DEFAULT_CONFIG_KEY_VALUES = [];

    protected const array DEFAULT_CONFIG_SCHEMA = [];

    protected const array DEFAULT_DTO_SCHEMA = [];

    protected const string DEFAULT_STUBS_DIR = 'stubs/gacela';

    protected const bool DEFAULT_VALIDATE_CONFIG_SCHEMA_ON_BOOT = false;

    protected const array DEFAULT_GENERIC_LISTENERS = [];

    protected const array DEFAULT_SPECIFIC_LISTENERS = [];

    protected const array DEFAULT_SERVICES_TO_EXTEND = [];

    protected const array DEFAULT_FACTORIES = [];

    protected const array DEFAULT_PROTECTED_SERVICES = [];

    protected const array DEFAULT_ALIASES = [];

    protected const array DEFAULT_CONTEXTUAL_BINDINGS = [];

    protected const array DEFAULT_HANDLER_REGISTRIES = [];

    protected const array DEFAULT_PLUGIN_STACKS = [];

    protected const array DEFAULT_PROVIDER_SERVICES_TO_EXTEND = [];

    protected const array DEFAULT_TAGS = [];

    protected const array DEFAULT_AFTER_RESOLVING_CALLBACKS = [];

    protected const array DEFAULT_LAZY_SERVICES = [];

    protected const array DEFAULT_DEFINITIONS = [];

    protected const array DEFAULT_GACELA_CONFIGS_TO_EXTEND = [];

    protected const array DEFAULT_PLUGINS = [];

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
     * @param ExternalServicesMap $externalServices
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
     * @return ExternalServicesMap
     */
    public function externalServices(): array
    {
        return [];
    }

    /**
     * Declaring no schema is the default: nothing is checked until a project
     * says what its configuration is supposed to contain.
     *
     * @return array<string, ConfigType>
     */
    public function getConfigSchema(): array
    {
        return self::DEFAULT_CONFIG_SCHEMA;
    }

    /**
     * Off by default, so bootstrap does no work a project did not ask for. The
     * schema is meant to be checked by `validate:config` and `doctor`; the boot
     * check is a local-development convenience.
     */
    public function shouldValidateConfigSchemaOnBoot(): bool
    {
        return self::DEFAULT_VALIDATE_CONFIG_SCHEMA_ON_BOOT;
    }

    /**
     * Where a project's published scaffolder stubs live, relative to the
     * application root unless an absolute path is given.
     */
    public function getStubsDir(): string
    {
        return self::DEFAULT_STUBS_DIR;
    }
}
