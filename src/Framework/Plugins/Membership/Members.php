<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use function array_map;
use function count;

/**
 * What one scan of the module paths found: the `#[Plugin]`, `#[Tag]` and
 * `#[AsListener]` declarations.
 *
 * @psalm-type MembersRows = array{
 *     plugins: list<array{0: class-string, 1: class-string, 2: int}>,
 *     tags?: list<array{0: string, 1: class-string}>,
 *     listeners?: list<array{0: class-string, 1: class-string, 2: string}>,
 * }
 */
final class Members
{
    /**
     * @param list<PluginMember> $plugins
     * @param list<TagMember> $tags
     * @param list<ListenerMember> $listeners
     */
    public function __construct(
        public readonly array $plugins = [],
        public readonly array $tags = [],
        public readonly array $listeners = [],
    ) {
    }

    /**
     * @param MembersRows $rows
     */
    public static function fromRows(array $rows): self
    {
        return new self(
            array_map(PluginMember::fromRow(...), $rows['plugins']),
            // A file warmed before `#[Tag]` or `#[AsListener]` existed lacks them.
            array_map(TagMember::fromRow(...), $rows['tags'] ?? []),
            array_map(ListenerMember::fromRow(...), $rows['listeners'] ?? []),
        );
    }

    /**
     * @return MembersRows
     */
    public function toRows(): array
    {
        return [
            'plugins' => array_map(static fn (PluginMember $member): array => $member->toRow(), $this->plugins),
            'tags' => array_map(static fn (TagMember $member): array => $member->toRow(), $this->tags),
            'listeners' => array_map(static fn (ListenerMember $member): array => $member->toRow(), $this->listeners),
        ];
    }

    public function count(): int
    {
        return count($this->plugins) + count($this->tags) + count($this->listeners);
    }
}
