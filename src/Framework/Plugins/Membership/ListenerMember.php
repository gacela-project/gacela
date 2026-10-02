<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

/**
 * One `#[AsListener]` declaration: a method that listens to an event.
 */
final class ListenerMember
{
    /**
     * @param class-string $event
     * @param class-string $class
     */
    public function __construct(
        public readonly string $event,
        public readonly string $class,
        public readonly string $method,
    ) {
    }

    /**
     * @param array{0: class-string, 1: class-string, 2: string} $row
     */
    public static function fromRow(array $row): self
    {
        return new self($row[0], $row[1], $row[2]);
    }

    /**
     * @return array{0: class-string, 1: class-string, 2: string}
     */
    public function toRow(): array
    {
        return [$this->event, $this->class, $this->method];
    }

    /**
     * By class and method name, so the order never depends on the order the
     * filesystem lists files in.
     */
    public static function compare(self $a, self $b): int
    {
        return [$a->event, $a->class, $a->method] <=> [$b->event, $b->class, $b->method];
    }
}
