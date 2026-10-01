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

    /** Which application, module paths and namespaces the memo answers for. */
    private static string $scope = '';

    /**
     * The declared plugins, followed by the attribute members not already among
     * them.
     *
     * @param list<class-string> $declared
     * @param string $scope changes when the application root, module paths or namespaces do
     * @param Closure(): list<PluginMember> $load called once per scope
     *
     * @return list<class-string>
     */
    public static function withMembers(string $contract, array $declared, string $scope, Closure $load): array
    {
        if (self::$pluginsByContract === null || self::$scope !== $scope) {
            // Assigned only once the load returned, so a scan that throws is
            // thrown again on the next ask instead of leaving every stack with
            // its declared members only.
            $byContract = [];
            foreach ($load() as $member) {
                $byContract[$member->contract][] = $member->plugin;
            }

            self::$pluginsByContract = $byContract;
            self::$scope = $scope;
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
        self::$scope = '';
    }
}
