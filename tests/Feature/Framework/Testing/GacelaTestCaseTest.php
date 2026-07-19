<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Testing;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Gacela;
use Gacela\Framework\Testing\GacelaTestCase;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use RuntimeException;
use Throwable;

use function count;

final class GacelaTestCaseTest extends GacelaTestCase
{
    public function test_bootstrap_with_config_exposes_key_values(): void
    {
        $this->bootstrapGacelaWithConfig(__DIR__, ['a-key' => 'a-value', 'an-int' => 42]);

        self::assertSame('a-value', Config::getInstance()->getString('a-key'));
        self::assertSame(42, Config::getInstance()->getInt('an-int'));
    }

    public function test_teardown_resets_the_config_singleton(): void
    {
        $this->bootstrapGacela(__DIR__);
        self::assertNotNull(Config::getInstance());

        $this->tearDown();

        $this->expectException(RuntimeException::class);
        Config::getInstance();
    }

    public function test_second_bootstrap_is_isolated_from_the_first(): void
    {
        $this->bootstrapGacelaWithConfig(__DIR__, ['key' => 'first']);
        self::assertSame('first', Config::getInstance()->getString('key'));
        self::assertSame('greeting', (new Module\Facade())->greet());

        $this->bootstrapGacelaWithConfig(__DIR__, ['key' => 'second']);

        self::assertSame('second', Config::getInstance()->getString('key'));
        // The module resolves again instead of reusing the first bootstrap's
        // cached instances: its service-resolved event is recorded anew.
        self::assertSame('greeting', (new Module\Facade())->greet());
        $this->assertServiceResolved(Module\Provider::GREETING);
    }

    public function test_recorded_events_are_reset_by_a_new_bootstrap(): void
    {
        $this->bootstrapGacela(__DIR__);
        $firstCount = count($this->recordedGacelaEvents());
        self::assertGreaterThan(0, $firstCount);

        $this->bootstrapGacela(__DIR__);

        // Only the second bootstrap's events remain: recording restarted.
        self::assertLessThanOrEqual($firstCount, count($this->recordedGacelaEvents()));
        self::assertNotSame([], $this->recordedGacelaEvents());
    }

    public function test_recorded_events_of_filters_by_event_class(): void
    {
        $this->bootstrapGacela(__DIR__, static function (GacelaConfig $config): void {
            $config->addBinding(StringValueInterface::class, StringValue::class);
        });
        self::assertSame('greeting', (new Module\Facade())->greet());

        $bindingEvents = $this->recordedGacelaEventsOf(BindingRegisteredEvent::class);

        self::assertNotSame([], $bindingEvents);
        self::assertContainsOnlyInstancesOf(BindingRegisteredEvent::class, $bindingEvents);
        // A real list: filtering must reindex, not keep the stream's offsets.
        self::assertSame(range(0, count($bindingEvents) - 1), array_keys($bindingEvents));
        // The generic stream contains more than binding events.
        self::assertGreaterThan(count($bindingEvents), count($this->recordedGacelaEvents()));
    }

    public function test_assert_service_resolved_passes_for_a_resolved_service(): void
    {
        $this->bootstrapGacela(__DIR__);
        self::assertSame('greeting', (new Module\Facade())->greet());

        $this->assertServiceResolved(Module\Provider::GREETING);
    }

    public function test_assert_service_resolved_fails_for_an_unknown_service(): void
    {
        $this->bootstrapGacela(__DIR__);

        $failed = false;

        try {
            $this->assertServiceResolved('unknown-service');
        } catch (Throwable $throwable) {
            $failed = true;
            self::assertStringContainsString('unknown-service', $throwable->getMessage());
        }

        self::assertTrue($failed, 'assertServiceResolved() should have failed');
    }

    public function test_assert_binding_registered_passes_for_a_registered_binding(): void
    {
        $this->bootstrapGacela(__DIR__, static function (GacelaConfig $config): void {
            $config->addBinding(StringValueInterface::class, StringValue::class);
        });
        // Force the main container to be built so bindings are registered.
        Gacela::get(StringValueInterface::class);

        $this->assertBindingRegistered(StringValueInterface::class);
    }

    public function test_assert_binding_registered_fails_for_an_unknown_binding(): void
    {
        $this->bootstrapGacela(__DIR__);

        $failed = false;

        try {
            $this->assertBindingRegistered('unknown-binding');
        } catch (Throwable $throwable) {
            $failed = true;
            self::assertStringContainsString('unknown-binding', $throwable->getMessage());
        }

        self::assertTrue($failed, 'assertBindingRegistered() should have failed');
    }

    public function test_custom_config_closure_runs_after_the_recorder_is_registered(): void
    {
        $seen = false;
        $this->bootstrapGacela(__DIR__, static function (GacelaConfig $config) use (&$seen): void {
            $seen = true;
            $config->addAppConfigKeyValue('from-closure', 'yes');
        });

        self::assertTrue($seen);
        self::assertSame('yes', Config::getInstance()->getString('from-closure'));
        self::assertNotSame([], $this->recordedGacelaEvents());
    }
}
