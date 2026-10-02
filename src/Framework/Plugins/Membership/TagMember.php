<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

/**
 * One `#[Tag]` declaration: a class that joins a tag.
 */
final class TagMember
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $class,
    ) {
    }

    /**
     * @param array{0: string, 1: class-string} $row
     */
    public static function fromRow(array $row): self
    {
        return new self($row[0], $row[1]);
    }

    /**
     * @return array{0: string, 1: class-string}
     */
    public function toRow(): array
    {
        return [$this->tag, $this->class];
    }

    /**
     * By class name, so the order never depends on the order the filesystem
     * lists files in.
     */
    public static function compare(self $a, self $b): int
    {
        return [$a->tag, $a->class] <=> [$b->tag, $b->class];
    }
}
