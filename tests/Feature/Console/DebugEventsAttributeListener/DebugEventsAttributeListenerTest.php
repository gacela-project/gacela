<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugEventsAttributeListener;

use Gacela\Console\Infrastructure\Command\DebugEventsCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugEventsAttributeListener\Fixture\OrderShippedEvent;
use GacelaTest\Feature\Console\DebugEventsAttributeListener\Fixture\ShippingNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * An `#[AsListener]` method is named nowhere in `gacela.php`, so without this
 * the event it handles reads as one nothing listens to.
 */
final class DebugEventsAttributeListenerTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_a_project_event_names_its_attribute_listeners(): void
    {
        $tester = new CommandTester(new DebugEventsCommand());
        $tester->execute(['--listened' => true]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('OrderShippedEvent', $display);
        self::assertStringContainsString('2 listeners via #[AsListener] ShippingNotifier::onOrderShipped() via #[AsListener] ShippingNotifier::onAnything()', $display);
    }

    public function test_the_json_lists_them_and_leaves_the_framework_events_alone(): void
    {
        $tester = new CommandTester(new DebugEventsCommand());
        $tester->execute(['--json' => true]);

        /** @var array{events: list<array{class: string, listeners: int, attributeListeners: list<string>}>} $document */
        $document = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        $byClass = [];
        foreach ($document['events'] as $event) {
            $byClass[$event['class']] = $event;
        }

        self::assertSame(
            // The order they run in.
            [ShippingNotifier::class . '::onOrderShipped()', ShippingNotifier::class . '::onAnything()'],
            $byClass[OrderShippedEvent::class]['attributeListeners'],
        );
        self::assertSame(2, $byClass[OrderShippedEvent::class]['listeners']);
        self::assertSame([], $byClass[ResolvedClassCachedEvent::class]['attributeListeners']);
        self::assertSame(0, $byClass[ResolvedClassCachedEvent::class]['listeners']);
    }
}
