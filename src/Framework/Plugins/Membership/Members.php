<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use function array_map;
use function count;

/**
 * What one scan of the module paths found: the `#[Plugin]` and `#[Tag]`
 * declarations.
 */
final class Members
{
    /**
     * @param list<PluginMember> $plugins
     * @param list<TagMember> $tags
     */
    public function __construct(
        public readonly array $plugins = [],
        public readonly array $tags = [],
    ) {
    }

    /**
     * @param array{plugins: list<array{0: class-string, 1: class-string, 2: int}>, tags: list<array{0: string, 1: class-string}>} $rows
     */
    public static function fromRows(array $rows): self
    {
        return new self(
            array_map(PluginMember::fromRow(...), $rows['plugins']),
            array_map(TagMember::fromRow(...), $rows['tags']),
        );
    }

    /**
     * @return array{plugins: list<array{0: class-string, 1: class-string, 2: int}>, tags: list<array{0: string, 1: class-string}>}
     */
    public function toRows(): array
    {
        return [
            'plugins' => array_map(static fn (PluginMember $member): array => $member->toRow(), $this->plugins),
            'tags' => array_map(static fn (TagMember $member): array => $member->toRow(), $this->tags),
        ];
    }

    public function count(): int
    {
        return count($this->plugins) + count($this->tags);
    }
}
