<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use Closure;

use function in_array;

/**
 * The `#[Plugin]` members of each stack, loaded once per process.
 *
 * Asked only when a declared stack is first resolved, so an application that
 * declares no stack never loads them.
 *
 * @internal
 */
final class PluginMembership
{
    /** @var array<string, list<class-string>>|null */
    private static ?array $pluginsByContract = null;

    /**
     * The declared plugins, followed by the attribute members not already among
     * them.
     *
     * @param list<class-string> $declared
     * @param Closure(): list<PluginMember> $load called on the first ask only
     *
     * @return list<class-string>
     */
    public static function withMembers(string $contract, array $declared, Closure $load): array
    {
        if (self::$pluginsByContract === null) {
            self::$pluginsByContract = [];
            foreach ($load() as $member) {
                self::$pluginsByContract[$member->contract][] = $member->plugin;
            }
        }

        $plugins = $declared;

        foreach (self::$pluginsByContract[$contract] ?? [] as $member) {
            if (!in_array($member, $plugins, true)) {
                $plugins[] = $member;
            }
        }

        return $plugins;
    }

    public static function resetCache(): void
    {
        self::$pluginsByContract = null;
    }
}
