<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\BuilderConfigurationInterface;
use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\Schema\ConfigType;
use Gacela\Framework\Dto\Schema\DtoType;
use Gacela\Framework\Dto\Schema\MalformedDtoSchemaException;

use function array_merge;
use function array_unique;
use function in_array;

/**
 * Merges individual properties into a SetupGacela instance.
 *
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 * @psalm-import-type ExternalServicesMap from BuilderConfigurationInterface
 * @psalm-import-type ServiceFactoryMap from ContainerConfigurationInterface
 * @psalm-import-type ServiceAliasMap from ContainerConfigurationInterface
 * @psalm-import-type HandlerRegistriesMap from ContainerConfigurationInterface
 * @psalm-import-type PluginStacksMap from ContainerConfigurationInterface
 * @psalm-import-type ProviderServicesToExtendMap from ContainerConfigurationInterface
 * @psalm-import-type TagsMap from ContainerConfigurationInterface
 * @psalm-import-type AfterResolvingMap from ContainerConfigurationInterface
 * @psalm-import-type DefinitionSources from ContainerConfigurationInterface
 * @psalm-import-type ContextualBindingsMap from ContainerConfigurationInterface
 * @psalm-import-type ConfigKeyValues from SetupGacelaInterface
 * @psalm-import-type SpecificListenersMap from \Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher
 */
final class PropertyMerger
{
    public function __construct(
        private readonly SetupGacela $setup,
    ) {
    }

    /**
     * @param ExternalServicesMap $list
     */
    public function mergeExternalServices(array $list): void
    {
        $current = $this->setup->externalServices();
        $this->setup->setExternalServices(array_merge($current, $list));
    }

    /**
     * @param list<string> $list
     */
    public function mergeProjectNamespaces(array $list): void
    {
        $current = $this->setup->getProjectNamespaces();
        $this->setup->setProjectNamespaces(array_merge($current, $list));
    }

    /**
     * Accumulated, and deduplicated: two sources refusing the same package are
     * agreeing, not refusing it twice, and the list is read back by `doctor`
     * and by `debug:container` where a repeated name reads as a mistake.
     *
     * @param list<string> $list
     */
    public function mergeDontDiscover(array $list): void
    {
        $current = $this->setup->getDontDiscover();
        $this->setup->setDontDiscover(array_values(array_unique(array_merge($current, $list))));
    }

    /**
     * Declaration order is the order of the chain, so a later source appends
     * dimensions rather than reordering the ones already declared.
     *
     * @param list<string> $list
     */
    public function mergeConfigDimensions(array $list): void
    {
        $current = $this->setup->getConfigDimensions();
        $merged = $current;

        foreach ($list as $variable) {
            if (!in_array($variable, $merged, true)) {
                $merged[] = $variable;
            }
        }

        $this->setup->setConfigDimensions($merged);
    }

    /**
     * @param ConfigKeyValues $list
     */
    public function mergeConfigKeyValues(array $list): void
    {
        $current = $this->setup->getConfigKeyValues();
        $this->setup->setConfigKeyValues(array_merge($current, $list));
    }

    /**
     * Per key, later wins: an extended config refining one key's declaration
     * says nothing about the keys it did not mention.
     *
     * @param array<string, ConfigType> $schema
     */
    public function mergeConfigSchema(array $schema): void
    {
        $current = $this->setup->getConfigSchema();
        $this->setup->setConfigSchema(array_merge($current, $schema));
    }

    /**
     * Union per property, unlike the config schema above.
     *
     * A config key has one owner, so later-wins is right for it. A shape does
     * not: the module that declared it first reads the same generated class, so
     * a later declarer redefining a property would break code that already
     * compiles against it. Adding a property is safe and is the point -- a
     * project extends a packaged shape without owning its file. Redefining one
     * is refused where it is declared.
     *
     * @param array<string, array<string, DtoType>> $schema
     */
    public function mergeDtoSchema(array $schema): void
    {
        $merged = $this->setup->getDtoSchema();

        foreach ($schema as $className => $properties) {
            foreach ($properties as $property => $type) {
                $existing = $merged[$className][$property] ?? null;

                if ($existing instanceof DtoType && !$existing->isSameShapeAs($type)) {
                    throw MalformedDtoSchemaException::conflictingRedeclaration($className, $property);
                }

                $merged[$className][$property] = $type;
            }
        }

        $this->setup->setDtoSchema($merged);
    }

    /**
     * @param list<class-string> $list
     */
    public function mergeGacelaConfigsToExtend(array $list): void
    {
        $current = $this->setup->getGacelaConfigsToExtend();
        /** @var list<class-string> $merged */
        $merged = array_values(array_unique(array_merge($current, $list)));
        $this->setup->setGacelaConfigsToExtend($merged);
    }

    /**
     * @param list<class-string|callable> $list
     */
    public function mergePlugins(array $list): void
    {
        $current = $this->setup->getPlugins();
        $this->setup->setPlugins(array_merge($current, $list));
    }

    /**
     * @param ServiceFactoryMap $list
     */
    public function mergeFactories(array $list): void
    {
        $current = $this->setup->getFactories();
        $this->setup->setFactories(array_merge($current, $list));
    }

    /**
     * @param ServiceFactoryMap $list
     */
    public function mergeProtectedServices(array $list): void
    {
        $current = $this->setup->getProtectedServices();
        $this->setup->setProtectedServices(array_merge($current, $list));
    }

    /**
     * @param ServiceAliasMap $list
     */
    public function mergeAliases(array $list): void
    {
        $current = $this->setup->getAliases();
        $this->setup->setAliases(array_merge($current, $list));
    }

    /**
     * @param ContextualBindingsMap $list
     */
    public function mergeContextualBindings(array $list): void
    {
        $this->setup->setContextualBindings(
            $this->mergeNested($this->setup->getContextualBindings(), $list),
        );
    }

    /**
     * @param HandlerRegistriesMap $list
     */
    public function mergeHandlerRegistries(array $list): void
    {
        $this->setup->setHandlerRegistries(
            $this->mergeNested($this->setup->getHandlerRegistries(), $list),
        );
    }

    /**
     * Extensions accumulate: two config sources that both decorate the same
     * Provider binding both run, in source order.
     *
     * @param ProviderServicesToExtendMap $list
     */
    public function mergeProviderServicesToExtend(array $list): void
    {
        $merged = $this->setup->getProviderServicesToExtend();

        foreach ($list as $providerClass => $byId) {
            foreach ($byId as $id => $extensions) {
                $merged[$providerClass][$id] = [
                    ...$merged[$providerClass][$id] ?? [],
                    ...$extensions,
                ];
            }
        }

        $this->setup->setProviderServicesToExtend($merged);
    }

    /**
     * A stack is a collection too, and an ordered one: the later setup appends
     * to what the earlier declared, seed first. A class declared twice appears
     * once, so a config source re-stating the seed does not run it twice.
     *
     * @param PluginStacksMap $list
     */
    public function mergePluginStacks(array $list): void
    {
        $merged = $this->setup->getPluginStacks();

        foreach ($list as $contract => $plugins) {
            // Appended one at a time rather than merged-then-deduplicated: the
            // result stays a list without a reindex step, and a class both
            // sources declared keeps the position the first one gave it.
            $current = $merged[$contract] ?? [];

            foreach ($plugins as $plugin) {
                if (!in_array($plugin, $current, true)) {
                    $current[] = $plugin;
                }
            }

            $merged[$contract] = $current;
        }

        $this->setup->setPluginStacks($merged);
    }

    /**
     * A tag is a collection, so the later setup adds to it rather than replacing
     * it -- and an id both setups declared appears once, matching what the
     * container's own tag registry does.
     *
     * @param TagsMap $list
     */
    public function mergeTags(array $list): void
    {
        $merged = $this->setup->getTags();

        foreach ($list as $tag => $ids) {
            $merged[$tag] = array_values(array_unique(array_merge($merged[$tag] ?? [], $ids)));
        }

        $this->setup->setTags($merged);
    }

    /**
     * Hooks accumulate per id and keep their registration order: a second setup
     * adding a hook for an id must not silence the first setup's.
     *
     * @param AfterResolvingMap $list
     */
    public function mergeAfterResolvingCallbacks(array $list): void
    {
        $merged = $this->setup->getAfterResolvingCallbacks();

        foreach ($list as $id => $callbacks) {
            $merged[$id] = array_merge($merged[$id] ?? [], $callbacks);
        }

        $this->setup->setAfterResolvingCallbacks($merged);
    }

    /**
     * Sources are an ordered layer stack, not a keyed map, so the later setup's
     * sources go on top: they are the overrides, and the container applies the
     * last one to register.
     *
     * @param DefinitionSources $list
     */
    public function mergeDefinitions(array $list): void
    {
        $this->setup->setDefinitions([...$this->setup->getDefinitions(), ...$list]);
    }

    /**
     * @param ServiceFactoryMap $list
     */
    public function mergeLazyServices(array $list): void
    {
        $current = $this->setup->getLazyServices();
        $this->setup->setLazyServices(array_merge($current, $list));
    }

    /**
     * Appended, so the base setup's listeners keep running and keep running
     * first -- the order they are registered in is the order they fire in.
     *
     * @param list<callable> $list
     */
    public function mergeGenericListeners(array $list): void
    {
        $current = $this->setup->getGenericListeners() ?? [];
        $this->setup->setGenericListeners(array_merge($current, $list));
    }

    /**
     * Per target, the way {@see mergeGenericListeners()} does for the whole
     * list: two sources naming the same event class both get to listen to it.
     *
     * @param SpecificListenersMap $list
     */
    public function mergeSpecificListeners(array $list): void
    {
        $merged = $this->setup->getSpecificListeners() ?? [];

        foreach ($list as $event => $listeners) {
            foreach ($listeners as $listener) {
                $merged[$event][] = $listener;
            }
        }

        $this->setup->setSpecificListeners($merged);
    }

    /**
     * Merge a two-level map, combining the inner maps key by key.
     *
     * @template TInner of array
     *
     * @param array<string,TInner> $current
     * @param array<string,TInner> $list
     *
     * @return array<string,TInner>
     */
    private function mergeNested(array $current, array $list): array
    {
        $merged = $current;

        foreach ($list as $key => $inner) {
            /** @var TInner $mergedInner */
            $mergedInner = array_merge($merged[$key] ?? [], $inner);
            $merged[$key] = $mergedInner;
        }

        return $merged;
    }
}
