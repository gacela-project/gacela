<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

use function array_sum;
use function strrpos;
use function substr;

/**
 * One event class, and what listens to it.
 */
final class EventInspection
{
    /**
     * @param class-string          $className
     * @param string                $group                the namespace under `Gacela\Framework\Event\`, e.g. `ClassResolver\Cache`; a project event carries its own namespace in full
     * @param bool                  $isAbstract           a target listeners can be registered against, never itself dispatched
     * @param bool                  $isHotPath            dispatched on every warm resolve
     * @param array<class-string, int> $matchedTargets    registered target => how many listeners it carries, for the targets that cover this event
     * @param int                   $genericListenerCount registerGenericListener() callables, which cover every event
     * @param EventSource           $source               who declared it: the framework, or the application
     */
    public function __construct(
        public readonly string $className,
        public readonly string $group,
        public readonly bool $isAbstract,
        public readonly bool $isHotPath,
        public readonly array $matchedTargets,
        public readonly int $genericListenerCount,
        public readonly EventSource $source,
    ) {
    }

    public function shortName(): string
    {
        $position = strrpos($this->className, '\\');

        return $position === false ? $this->className : substr($this->className, $position + 1);
    }

    public function specificListenerCount(): int
    {
        return array_sum($this->matchedTargets);
    }

    public function listenerCount(): int
    {
        return $this->specificListenerCount() + $this->genericListenerCount;
    }

    /**
     * Something listens to this event *and* the event is one that gets
     * dispatched.
     *
     * An abstract event matches its own registration -- `is_a()` says a class is
     * itself -- and nothing ever dispatches one, so counting it as watched
     * reports a listener in five places when it runs in four. The registration
     * is named against each concrete event it covers, which is where it fires.
     */
    public function isWatched(): bool
    {
        return !$this->isAbstract && $this->listenerCount() > 0;
    }
}
