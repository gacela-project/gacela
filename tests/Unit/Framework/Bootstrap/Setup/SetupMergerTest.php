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
}
