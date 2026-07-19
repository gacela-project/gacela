<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\Lifecycle;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

/**
 * Runs the copy-paste recipes from docs/events.md end-to-end so the
 * documentation cannot silently rot when the event API changes.
 */
final class EventDocExamplesTest extends TestCase
{
    public function test_recipe_log_every_resolved_class(): void
    {
        /** @var list<string> $logged */
        $logged = [];

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$logged): void {
            $config->resetInMemoryCache();

            $config->registerGenericListener(static function (GacelaEventInterface $event) use (&$logged): void {
                if ($event instanceof AbstractGacelaClassResolverEvent) {
                    $logged[] = $event->toString();
                }
            });
        });

        (new Module\Facade())->greet();

        self::assertNotEmpty($logged);
        self::assertStringContainsString('classInfo:', $logged[0]);
    }

    public function test_recipe_time_bootstrap(): void
    {
        /** @var list<float> $durations */
        $durations = [];

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$durations): void {
            $config->resetInMemoryCache();

            $config->registerSpecificListener(
                GacelaBootstrapFinishedEvent::class,
                static function (GacelaBootstrapFinishedEvent $event) use (&$durations): void {
                    $durations[] = $event->durationMs();
                },
            );
        });

        self::assertCount(1, $durations);
        self::assertGreaterThan(0.0, $durations[0]);
    }
}
