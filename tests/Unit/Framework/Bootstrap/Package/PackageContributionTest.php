<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Package;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\Package\PackageContribution;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigFileAssembler;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * What `debug:container` prints under a package's name.
 */
final class PackageContributionTest extends TestCase
{
    public function test_a_config_that_declares_nothing_says_so(): void
    {
        $contribution = $this->contributionOf(static function (GacelaConfig $config): void {
        });

        self::assertTrue($contribution->isEmpty());
        self::assertSame([], $contribution->items());
        self::assertSame('nothing', $contribution->summary());
    }

    public function test_a_binding_is_named_by_the_id_it_answers(): void
    {
        $contribution = $this->contributionOf(static function (GacelaConfig $config): void {
            $config->addBinding(StringValueInterface::class, StringValue::class);
        });

        self::assertFalse($contribution->isEmpty());
        self::assertSame(['bindings' => [StringValueInterface::class]], $contribution->items());
    }

    /**
     * One entry per member, not per contract: the contract is the extension
     * point, and what a reader is looking for is what was added to it.
     */
    public function test_every_plugin_stack_member_is_named_with_its_contract(): void
    {
        $contribution = $this->contributionOf(static function (GacelaConfig $config): void {
            $config->addPluginStack(StringValueInterface::class, [StringValue::class, StringValue::class]);
        });

        self::assertSame([
            'plugin stacks' => [
                StringValueInterface::class . ' => ' . StringValue::class,
                StringValueInterface::class . ' => ' . StringValue::class,
            ],
        ], $contribution->items());
    }

    public function test_listeners_are_counted_per_event_and_the_generic_ones_together(): void
    {
        $contribution = $this->contributionOf(static function (GacelaConfig $config): void {
            $config->registerSpecificListener(GacelaBootstrapFinishedEvent::class, static fn (): null => null);
            $config->registerSpecificListener(GacelaBootstrapFinishedEvent::class, static fn (): null => null);
            $config->registerGenericListener(static fn (): null => null);
        });

        self::assertSame([
            'listeners' => [GacelaBootstrapFinishedEvent::class . ' (2)', 'every event (1)'],
        ], $contribution->items());
    }

    /**
     * A plugin is a class-string or a closure, and a closure has no name.
     */
    public function test_a_closure_plugin_is_labelled_rather_than_named(): void
    {
        $contribution = $this->contributionOf(static function (GacelaConfig $config): void {
            $config->addPlugin(static fn (): null => null);
        });

        self::assertSame(['plugins' => ['closure']], $contribution->items());
    }

    public function test_the_summary_counts_each_kind(): void
    {
        $contribution = $this->contributionOf(static function (GacelaConfig $config): void {
            $config->addBinding(StringValueInterface::class, StringValue::class);
            $config->addAppConfigKeyValue('audit.enabled', true);
            $config->addAppConfigKeyValue('audit.level', 'debug');
        });

        self::assertSame(['bindings', 'config keys'], array_keys($contribution->items()));
        self::assertSame('1 bindings, 2 config keys', $contribution->summary());
    }

    /**
     * A config path resolves against the *application* root, which a package
     * cannot know anything about, so it is deliberately not one of the kinds.
     */
    public function test_an_app_config_path_is_not_reported_as_a_contribution(): void
    {
        $contribution = $this->contributionOf(static function (GacelaConfig $config): void {
            $config->addAppConfig('config/*.php');
        });

        self::assertTrue($contribution->isEmpty());
    }

    /**
     * @param callable(GacelaConfig):void $configFn
     */
    private function contributionOf(callable $configFn): PackageContribution
    {
        $setup = SetupGacela::fromCallable($configFn);

        return PackageContribution::of($setup, GacelaConfigFileAssembler::assemble($setup));
    }
}
