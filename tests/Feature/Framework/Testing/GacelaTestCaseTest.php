<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Testing;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Exception\GacelaNotBootstrappedException;
use Gacela\Framework\Gacela;
use Gacela\Framework\Testing\GacelaTestCase;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Exception as PHPUnitException;
use RuntimeException;

use function count;
use function sprintf;

final class GacelaTestCaseTest extends GacelaTestCase
{
    /**
     * The failure a test gets when it bootstrapped Gacela directly.
     *
     * These assertions read events, and only bootstrapGacela() registers the
     * listener that collects them -- so a test migrated to this base class
     * while keeping its own Gacela::bootstrap() call recorded nothing, and was
     * told its service "was not resolved" when the service resolved perfectly
     * and nothing was watching.
     */
    public function test_asserting_without_bootstrapping_through_the_base_class_names_the_cause(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $message = self::failureMessageOf(
            fn (): mixed => $this->assertServiceResolved(StringValueInterface::class),
        );

        self::assertStringContainsString('No Gacela events were recorded', $message);
        self::assertStringContainsString('bootstrapGacela', $message);
        self::assertStringNotContainsString('was not resolved', $message);
    }

    public function test_the_binding_assertion_names_the_same_cause(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $message = self::failureMessageOf(
            fn (): mixed => $this->assertBindingRegistered(StringValueInterface::class),
        );

        self::assertStringContainsString('No Gacela events were recorded', $message);
    }

    /**
     * The guard must not swallow the assertion it guards: once events are
     * being recorded, a service that really was not resolved still says so.
     */
    public function test_a_genuinely_unresolved_service_still_reports_itself(): void
    {
        $this->bootstrapGacela(__DIR__);

        $message = self::failureMessageOf(
            fn (): mixed => $this->assertServiceResolved('never-resolved-id'),
        );

        self::assertStringContainsString('"never-resolved-id" was not resolved', $message);
        self::assertStringNotContainsString('No Gacela events were recorded', $message);
    }

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

        // The typed exception, not the base class: `tearDown()` leaving the
        // process unbootstrapped is the same condition `Gacela::container()`
        // and `Gacela::rootDir()` report, and asserting the base class here
        // would pass for any RuntimeException at all.
        $this->expectException(GacelaNotBootstrappedException::class);
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

        $message = self::failureMessageOf(fn () => $this->assertServiceResolved('unknown-service'));

        self::assertStringContainsString('unknown-service', $message);
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

        $message = self::failureMessageOf(fn () => $this->assertBindingRegistered('unknown-binding'));

        self::assertStringContainsString('unknown-binding', $message);
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

    public function test_assert_event_dispatched_passes_for_a_framework_event(): void
    {
        $this->bootstrapGacela(__DIR__);

        $this->assertEventDispatched(GacelaBootstrapFinishedEvent::class);
    }

    /**
     * The point of the assertion: an event the *application* dispatched, through
     * the dispatcher its module was given. Nothing about the recording is
     * framework-specific, and a test of a project's own events should not have
     * to build its own listener to see them.
     */
    public function test_assert_event_dispatched_passes_for_a_project_event(): void
    {
        $this->bootstrapGacela(__DIR__);

        (new Module\Facade())->announce('Ada');

        $this->assertEventDispatched(Module\GreetedEvent::class);
    }

    /**
     * The same inheritance rule the dispatcher matches by, so an assertion can
     * name a family: `GacelaEventInterface::class` is every event there is.
     */
    public function test_assert_event_dispatched_matches_by_inheritance(): void
    {
        $this->bootstrapGacela(__DIR__);

        (new Module\Facade())->announce('Ada');

        $this->assertEventDispatched(GacelaEventInterface::class);
    }

    public function test_assert_event_dispatched_fails_naming_the_event_that_never_arrived(): void
    {
        $this->bootstrapGacela(__DIR__);

        $message = self::failureMessageOf(
            fn (): mixed => $this->assertEventDispatched(Module\GreetedEvent::class),
        );

        self::assertStringContainsString(Module\GreetedEvent::class, $message);
        // And what did arrive, which is usually where the answer is: a listener
        // wired to the wrong class, or a flow that stopped earlier than the
        // test assumed.
        self::assertStringContainsString(GacelaBootstrapFinishedEvent::class, $message);
        self::assertStringNotContainsString('No Gacela events were recorded', $message);
    }

    /**
     * Same guard as the other two assertions: a test that bootstrapped Gacela
     * itself records nothing, and must be told that rather than that its event
     * did not happen.
     */
    public function test_assert_event_dispatched_names_the_missing_recording_first(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $message = self::failureMessageOf(
            fn (): mixed => $this->assertEventDispatched(GacelaBootstrapFinishedEvent::class),
        );

        self::assertStringContainsString('No Gacela events were recorded', $message);
        self::assertStringContainsString('bootstrapGacela', $message);
    }

    public function test_recorded_events_of_sees_a_project_event(): void
    {
        $this->bootstrapGacela(__DIR__);

        (new Module\Facade())->announce('Grace');

        $greeted = $this->recordedGacelaEventsOf(Module\GreetedEvent::class);

        self::assertCount(1, $greeted);
        self::assertSame('Grace', $greeted[0]->name());
    }

    public function test_failure_message_of_returns_what_the_failed_assertion_said(): void
    {
        $message = self::failureMessageOf(static fn () => self::assertTrue(false, 'Invoice INV-1 is not paid'));

        self::assertStringStartsWith('Invoice INV-1 is not paid', $message);
    }

    public function test_failure_message_of_fails_when_the_assertion_passes(): void
    {
        $message = self::failureMessageOf(
            static fn (): string => self::failureMessageOf(static fn () => self::assertTrue(true)),
        );

        self::assertSame('The assertion was expected to fail, and passed.', $message);
    }

    public function test_failure_message_of_hands_a_skip_back_to_phpunit(): void
    {
        self::assertSame('skipped inside', $this->rethrownMessageOf(static fn () => self::markTestSkipped('skipped inside')));
    }

    public function test_failure_message_of_hands_an_incomplete_back_to_phpunit(): void
    {
        self::assertSame('incomplete inside', $this->rethrownMessageOf(static fn () => self::markTestIncomplete('incomplete inside')));
    }

    public function test_failure_message_of_lets_a_project_exception_through(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not an assertion');

        self::failureMessageOf(static function (): never {
            throw new RuntimeException('not an assertion');
        });
    }

    /**
     * The message of what failureMessageOf() threw rather than returned.
     *
     * Caught here, because an expected skip or incomplete is recorded by
     * PHPUnit as skipped or incomplete, never as passed.
     *
     * @param Closure():mixed $assertion
     */
    private function rethrownMessageOf(Closure $assertion): string
    {
        try {
            $returned = self::failureMessageOf($assertion);
        } catch (PHPUnitException $phpUnitException) {
            return $phpUnitException->getMessage();
        }

        self::fail(sprintf('failureMessageOf() returned "%s" instead of rethrowing.', $returned));
    }
}
