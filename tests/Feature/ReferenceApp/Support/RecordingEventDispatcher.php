<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Support;

use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

/**
 * A dispatcher an application hands to Gacela instead of letting it build one --
 * the seam a project uses to bridge these events onto its own bus.
 */
final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<class-string> */
    private array $received = [];

    public function dispatch(object $event): void
    {
        $this->received[] = $event::class;
    }

    public function hasListeners(string $eventClass): bool
    {
        return true;
    }

    /**
     * @return list<class-string>
     */
    public function received(): array
    {
        return $this->received;
    }
}
