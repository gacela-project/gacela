<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

/**
 * One `#[Plugin]` declaration: a class that joins the stack of a contract.
 */
final class PluginMember
{
    /**
     * @param class-string $contract
     * @param class-string $plugin
     */
    public function __construct(
        public readonly string $contract,
        public readonly string $plugin,
        public readonly int $priority,
    ) {
    }

    /**
     * @param array{0: class-string, 1: class-string, 2: int} $row
     */
    public static function fromRow(array $row): self
    {
        return new self($row[0], $row[1], $row[2]);
    }

    /**
     * @return array{0: class-string, 1: class-string, 2: int}
     */
    public function toRow(): array
    {
        return [$this->contract, $this->plugin, $this->priority];
    }

    /**
     * Highest priority first, then by class name, so the order never depends on
     * the order the filesystem lists files in.
     */
    public static function compare(self $a, self $b): int
    {
        return [$b->priority, $a->plugin] <=> [$a->priority, $b->plugin];
    }
}
