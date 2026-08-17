<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Support;

use Gacela\Framework\Event\GacelaEventInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use function array_map;

/**
 * The bus a hosted application already has: PSR-14 and nothing else.
 *
 * Unlike {@see RecordingEventDispatcher} it implements no Gacela interface and
 * has no `hasListeners()` to implement -- PSR-14 has no such method. Handing one
 * of these to `setEventDispatcher()` is what a Symfony or Laravel project does,
 * and Gacela wraps it.
 */
final class RecordingPsr14Bus implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $received = [];

    public function dispatch(object $event): object
    {
        $this->received[] = $event;

        return $event;
    }

    /**
     * @return list<class-string>
     */
    public function receivedClasses(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->received);
    }

    /**
     * What the events of one class said about themselves, in dispatch order.
     *
     * `toString()` rather than the object, so an assertion reads as the fact the
     * event carried instead of an identity nothing else in the test holds.
     *
     * @param class-string $eventClass
     *
     * @return list<string>
     */
    public function descriptionsOf(string $eventClass): array
    {
        $descriptions = [];

        foreach ($this->received as $event) {
            if ($event instanceof $eventClass && $event instanceof GacelaEventInterface) {
                $descriptions[] = $event->toString();
            }
        }

        return $descriptions;
    }
}
