<?php

declare(strict_types=1);

namespace Gacela\Console\Application\DebugPlugins;

use Gacela\Framework\Plugins\Membership\Members;

use function array_key_exists;
use function in_array;
use function sprintf;

/**
 * Who joins each plugin stack and tag, and who listens by attribute, with
 * where each one was declared: the order the runtime reads them in.
 *
 * @psalm-type StackRow = array{contract: string, member: string, source: string}
 * @psalm-type TagRow = array{tag: string, id: string, source: string}
 * @psalm-type ListenerRow = array{event: string, listener: string}
 */
final class MembershipReport
{
    public const SOURCE_DECLARED = 'gacela.php';

    /**
     * @param array<string, list<string>> $pluginStacks as `gacela.php` declares them, packages included
     * @param array<string, list<string>> $tags as `gacela.php` declares them, packages included
     *
     * @return array{stacks: list<StackRow>, tags: list<TagRow>, listeners: list<ListenerRow>, problems: list<string>}
     */
    public static function of(array $pluginStacks, array $tags, Members $members): array
    {
        return [
            'stacks' => self::stacks($pluginStacks, $members),
            'tags' => self::tags($tags, $members),
            'listeners' => self::listeners($members),
            'problems' => $members->problems,
        ];
    }

    /**
     * @param array<string, list<string>> $pluginStacks
     *
     * @return list<StackRow>
     */
    private static function stacks(array $pluginStacks, Members $members): array
    {
        $rows = [];
        foreach ($pluginStacks as $contract => $declared) {
            foreach ($declared as $plugin) {
                $rows[] = ['contract' => $contract, 'member' => $plugin, 'source' => self::SOURCE_DECLARED];
            }

            foreach ($members->plugins as $member) {
                if ($member->contract === $contract && !in_array($member->plugin, $declared, true)) {
                    $rows[] = ['contract' => $contract, 'member' => $member->plugin, 'source' => sprintf('#[Plugin] priority %d', $member->priority)];
                }
            }
        }

        // Shown rather than dropped: the class looks registered and is never read.
        foreach ($members->plugins as $member) {
            if (!array_key_exists($member->contract, $pluginStacks)) {
                $rows[] = ['contract' => $member->contract, 'member' => $member->plugin, 'source' => '#[Plugin], stack not declared: never read'];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, list<string>> $tags
     *
     * @return list<TagRow>
     */
    private static function tags(array $tags, Members $members): array
    {
        $attributeIdsByTag = [];
        foreach ($members->tags as $member) {
            $attributeIdsByTag[$member->tag][] = $member->class;
        }

        $rows = [];
        foreach ($tags + $attributeIdsByTag as $tag => $unused) {
            $declared = $tags[$tag] ?? [];
            foreach ($declared as $id) {
                $rows[] = ['tag' => $tag, 'id' => $id, 'source' => self::SOURCE_DECLARED];
            }

            foreach ($attributeIdsByTag[$tag] ?? [] as $class) {
                if (!in_array($class, $declared, true)) {
                    $rows[] = ['tag' => $tag, 'id' => $class, 'source' => '#[Tag]'];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<ListenerRow>
     */
    private static function listeners(Members $members): array
    {
        $rows = [];
        foreach ($members->listeners as $member) {
            $rows[] = ['event' => $member->event, 'listener' => sprintf('%s::%s()', $member->class, $member->method)];
        }

        return $rows;
    }
}
