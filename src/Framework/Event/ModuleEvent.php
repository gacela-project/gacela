<?php

declare(strict_types=1);

namespace Gacela\Framework\Event;

use function sprintf;

/**
 * Base class for module events.
 * Extend this for domain events that need to be communicated across modules.
 */
abstract class ModuleEvent implements GacelaEventInterface
{
    private readonly float $timestamp;

    public function __construct()
    {
        $this->timestamp = microtime(true);
    }

    /**
     * Get the event name (defaults to class name).
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * Get the timestamp when the event was created.
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function toString(): string
    {
        return sprintf(
            '%s (timestamp: %s)',
            $this->getName(),
            date('Y-m-d H:i:s', (int) $this->timestamp),
        );
    }
}
