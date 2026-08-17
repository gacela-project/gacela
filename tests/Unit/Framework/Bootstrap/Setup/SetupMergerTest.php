<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SetupMergerTest extends TestCase
{
    public function test_merge_factories_from_two_setups(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addFactory('service-a', static fn (): stdClass => new stdClass());
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addFactory('service-b', static fn (): stdClass => new stdClass());
        });

        $merged = $setup1->merge($setup2);

        $factories = $merged->getFactories();
        self::assertArrayHasKey('service-a', $factories);
        self::assertArrayHasKey('service-b', $factories);
        self::assertCount(2, $factories);
    }

    public function test_merge_factories_later_values_override_earlier(): void
    {
        $factory1 = static fn (): stdClass => new stdClass();
        $factory2 = static fn (): stdClass => new stdClass();

        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($factory1): void {
            $config->addFactory('service', $factory1);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($factory2): void {
            $config->addFactory('service', $factory2);
        });

        $merged = $setup1->merge($setup2);

        $factories = $merged->getFactories();
        self::assertSame($factory2, $factories['service'], 'Later factory should override earlier one');
    }

    public function test_merge_protected_services_from_two_setups(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addProtected('service-a', static fn (): string => 'A');
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addProtected('service-b', static fn (): string => 'B');
        });

        $merged = $setup1->merge($setup2);

        $protectedServices = $merged->getProtectedServices();
        self::assertArrayHasKey('service-a', $protectedServices);
        self::assertArrayHasKey('service-b', $protectedServices);
        self::assertCount(2, $protectedServices);
    }

    public function test_merge_protected_services_later_values_override_earlier(): void
    {
        $protected1 = static fn (): string => 'first';
        $protected2 = static fn (): string => 'second';

        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($protected1): void {
            $config->addProtected('service', $protected1);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($protected2): void {
            $config->addProtected('service', $protected2);
        });

        $merged = $setup1->merge($setup2);

        $protectedServices = $merged->getProtectedServices();
        self::assertSame($protected2, $protectedServices['service'], 'Later protected service should override earlier one');
    }

    public function test_merge_aliases_from_two_setups(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addAlias('alias-a', 'service-a');
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addAlias('alias-b', 'service-b');
        });

        $merged = $setup1->merge($setup2);

        $aliases = $merged->getAliases();
        self::assertArrayHasKey('alias-a', $aliases);
        self::assertArrayHasKey('alias-b', $aliases);
        self::assertSame('service-a', $aliases['alias-a']);
        self::assertSame('service-b', $aliases['alias-b']);
        self::assertCount(2, $aliases);
    }

    public function test_merge_aliases_later_values_override_earlier(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addAlias('my-alias', 'original-service');
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addAlias('my-alias', 'new-service');
        });

        $merged = $setup1->merge($setup2);

        $aliases = $merged->getAliases();
        self::assertSame('new-service', $aliases['my-alias'], 'Later alias should override earlier one');
    }

    public function test_merge_all_container_features_together(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addFactory('factory-1', static fn (): stdClass => new stdClass());
            $config->addProtected('protected-1', static fn (): string => 'P1');
            $config->addAlias('alias-1', 'service-1');
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addFactory('factory-2', static fn (): stdClass => new stdClass());
            $config->addProtected('protected-2', static fn (): string => 'P2');
            $config->addAlias('alias-2', 'service-2');
        });

        $merged = $setup1->merge($setup2);

        self::assertCount(2, $merged->getFactories());
        self::assertCount(2, $merged->getProtectedServices());
        self::assertCount(2, $merged->getAliases());
    }

    public function test_merge_only_if_property_changed(): void
    {
        // Setup1 with a factory
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addFactory('service-a', static fn (): stdClass => new stdClass());
        });

        // Setup2 with no changes (empty config)
        $setup2 = new SetupGacela();

        $merged = $setup1->merge($setup2);

        // Should still have setup1's factory since setup2 didn't change the property
        $factories = $merged->getFactories();
        self::assertArrayHasKey('service-a', $factories);
        self::assertCount(1, $factories);
    }

    public function test_merge_contextual_bindings_from_two_setups(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->when(stdClass::class)
                ->needs('LoggerInterface')
                ->give('FileLogger');
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->when('OtherClass')
                ->needs('CacheInterface')
                ->give('RedisCache');
        });

        $merged = $setup1->merge($setup2);

        $contextualBindings = $merged->getContextualBindings();
        self::assertArrayHasKey(stdClass::class, $contextualBindings);
        self::assertArrayHasKey('OtherClass', $contextualBindings);
        self::assertSame('FileLogger', $contextualBindings[stdClass::class]['LoggerInterface']);
        self::assertSame('RedisCache', $contextualBindings['OtherClass']['CacheInterface']);
    }

    public function test_merge_contextual_bindings_for_same_class_different_interfaces(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->when(stdClass::class)
                ->needs('LoggerInterface')
                ->give('FileLogger');
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->when(stdClass::class)
                ->needs('CacheInterface')
                ->give('RedisCache');
        });

        $merged = $setup1->merge($setup2);

        $contextualBindings = $merged->getContextualBindings();
        self::assertArrayHasKey(stdClass::class, $contextualBindings);
        self::assertCount(2, $contextualBindings[stdClass::class]);
        self::assertSame('FileLogger', $contextualBindings[stdClass::class]['LoggerInterface']);
        self::assertSame('RedisCache', $contextualBindings[stdClass::class]['CacheInterface']);
    }

    public function test_merge_tags_from_two_setups(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->tag('service-a', 'tag-a');
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->tag('service-b', 'tag-b');
        });

        $merged = $setup1->merge($setup2);

        $tags = $merged->getTags();
        self::assertSame(['service-a'], $tags['tag-a'] ?? null);
        self::assertSame(['service-b'], $tags['tag-b'] ?? null);
    }

    public function test_merge_the_same_tag_from_two_setups_unions_its_ids(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->tag(['service-a', 'shared'], 'validators');
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->tag(['shared', 'service-b'], 'validators');
        });

        $merged = $setup1->merge($setup2);

        // A tag is a collection, so the later setup adds to it rather than
        // replacing it -- and an id both setups declared appears once.
        self::assertSame(
            ['service-a', 'shared', 'service-b'],
            $merged->getTags()['validators'] ?? null,
        );
    }

    public function test_merge_after_resolving_callbacks_from_two_setups(): void
    {
        $first = static function (): void {};
        $second = static function (): void {};

        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($first): void {
            $config->afterResolving(stdClass::class, $first);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($second): void {
            $config->afterResolving(stdClass::class, $second);
        });

        $merged = $setup1->merge($setup2);

        // Hooks accumulate per id and keep registration order: the second setup
        // adds to the first's rather than silencing it.
        self::assertSame([$first, $second], $merged->getAfterResolvingCallbacks()[stdClass::class] ?? null);
    }

    public function test_merge_after_resolving_callbacks_for_different_ids(): void
    {
        $onStdClass = static function (): void {};
        $onOther = static function (): void {};

        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($onStdClass): void {
            $config->afterResolving(stdClass::class, $onStdClass);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($onOther): void {
            $config->afterResolving('OtherClass', $onOther);
        });

        $callbacks = $setup1->merge($setup2)->getAfterResolvingCallbacks();

        self::assertSame([$onStdClass], $callbacks[stdClass::class] ?? null);
        self::assertSame([$onOther], $callbacks['OtherClass'] ?? null);
    }

    public function test_merge_definitions_from_two_setups(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->loadDefinitions(['id' => ['value' => 'base']]);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->loadDefinitions(['id' => ['value' => 'override']]);
        });

        $merged = $setup1->merge($setup2);

        // Sources are an ordered layer stack, not a keyed map: the later setup's
        // source goes on top rather than replacing the stack, so the container
        // still applies the base before the override.
        self::assertSame(
            [['id' => ['value' => 'base']], ['id' => ['value' => 'override']]],
            $merged->getDefinitions(),
        );
    }

    public function test_a_setup_that_declares_no_definitions_does_not_drop_the_originals(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->loadDefinitions(['id' => ['value' => 'base']]);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue('unrelated', true);
        });

        self::assertSame(
            [['id' => ['value' => 'base']]],
            $setup1->merge($setup2)->getDefinitions(),
        );
    }

    /**
     * `gacela.php` is where a project writes this, beside `setProjectNamespaces()`
     * -- and until the merger carried it, writing it there did nothing: every
     * command went on walking the whole application root, and `doctor` reported
     * the closure's paths as the ones being scanned.
     */
    public function test_app_module_paths_declared_by_the_second_setup_are_the_ones_scanned(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue('unrelated', true);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->setAppModulePaths(['src/Billing', 'src/Customer']);
        });

        self::assertSame(['src/Billing', 'src/Customer'], $setup1->merge($setup2)->getAppModulePaths());
    }

    /**
     * Replaced, not appended: the list names every directory to scan, so a
     * source that writes it is answering the whole question.
     */
    public function test_app_module_paths_replace_rather_than_accumulate(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->setAppModulePaths(['src/Legacy']);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->setAppModulePaths(['src/Billing']);
        });

        self::assertSame(['src/Billing'], $setup1->merge($setup2)->getAppModulePaths());
    }

    public function test_a_setup_that_declares_no_app_module_paths_does_not_drop_the_originals(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->setAppModulePaths(['src/Billing']);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue('unrelated', true);
        });

        self::assertSame(['src/Billing'], $setup1->merge($setup2)->getAppModulePaths());
    }

    /**
     * The opposite of the app module paths above: `dontDiscover()` accumulates.
     *
     * A bootstrap closure and a `gacela.php` that each refuse a package are
     * refusing both, and the one that ran second is not overruling the other --
     * a refusal is not a list of everything to refuse, it is one decision. A
     * name both of them wrote appears once.
     */
    public function test_dont_discover_accumulates_across_two_setups(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->dontDiscover(['acme/legacy']);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->dontDiscover(['acme/legacy', 'acme/older']);
        });

        self::assertSame(['acme/legacy', 'acme/older'], $setup1->merge($setup2)->getDontDiscover());
    }

    public function test_a_setup_that_refuses_nothing_does_not_drop_the_refusals_already_made(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->dontDiscover(['acme/legacy']);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue('unrelated', true);
        });

        self::assertSame(['acme/legacy'], $setup1->merge($setup2)->getDontDiscover());
    }

    public function test_merge_handler_registries_from_two_setups(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addHandlerRegistry('registry-a', ['handler-1' => stdClass::class]);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->addHandlerRegistry('registry-b', ['handler-2' => stdClass::class]);
        });

        $merged = $setup1->merge($setup2);

        $registries = $merged->getHandlerRegistries();
        self::assertArrayHasKey('registry-a', $registries);
        self::assertArrayHasKey('registry-b', $registries);
    }

    public function test_merge_lazy_services_from_two_setups(): void
    {
        $lazyA = static fn (): stdClass => new stdClass();
        $lazyB = static fn (): stdClass => new stdClass();

        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($lazyA): void {
            $config->addLazy('lazy-a', $lazyA);
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($lazyB): void {
            $config->addLazy('lazy-b', $lazyB);
        });

        $merged = $setup1->merge($setup2);

        $lazyServices = $merged->getLazyServices();
        self::assertSame($lazyA, $lazyServices['lazy-a'] ?? null);
        self::assertSame($lazyB, $lazyServices['lazy-b'] ?? null);
    }

    public function test_merge_gacela_configs_to_extend_skipped_when_other_has_no_changes(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->extendGacelaConfig(\Fixtures\CustomGacelaConfig::class);
        });
        $setup2 = new SetupGacela();

        $merged = $setup1->merge($setup2);

        self::assertSame(
            [\Fixtures\CustomGacelaConfig::class],
            $merged->getGacelaConfigsToExtend(),
        );
    }

    public function test_merge_gacela_configs_to_extend_applied_when_other_has_changes(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->extendGacelaConfig(\Fixtures\CustomGacelaConfig::class);
        });
        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->extendGacelaConfig(\GacelaTest\Unit\Framework\Bootstrap\AnotherSetupFixtureConfig::class);
        });

        $merged = $setup1->merge($setup2);

        self::assertSame(
            [
                \Fixtures\CustomGacelaConfig::class,
                \GacelaTest\Unit\Framework\Bootstrap\AnotherSetupFixtureConfig::class,
            ],
            $merged->getGacelaConfigsToExtend(),
        );
    }

    public function test_merge_contextual_bindings_later_values_override_earlier(): void
    {
        $setup1 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->when(stdClass::class)
                ->needs('LoggerInterface')
                ->give('FileLogger');
        });

        $setup2 = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->when(stdClass::class)
                ->needs('LoggerInterface')
                ->give('DatabaseLogger');
        });

        $merged = $setup1->merge($setup2);

        $contextualBindings = $merged->getContextualBindings();
        self::assertSame('DatabaseLogger', $contextualBindings[stdClass::class]['LoggerInterface'], 'Later contextual binding should override earlier one');
    }
}
