<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture;

use Gacela\Framework\Event\GacelaEventInterface;

/**
 * Names the interface without being one: a listener type-hints the events it
 * receives, and so does anything else that handles them. The pre-filter cannot
 * tell the difference, and is not supposed to.
 */
final class RecordingListener
{
    /** @var list<string> */
    private array $recorded = [];

    public function __invoke(GacelaEventInterface $event): void
    {
        $this->recorded[] = $event->toString();
    }

    /**
     * @return list<string>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }
}
