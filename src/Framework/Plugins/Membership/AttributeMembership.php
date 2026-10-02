<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use Closure;

use function in_array;

/**
 * The `#[Plugin]` and `#[Tag]` members, loaded once per process.
 *
 * Asked only when a declared stack is first resolved or a tag first read, so
 * an application that does neither never loads them.
 *
 * @internal
 */
final class AttributeMembership
{
    /** @var array<string, list<class-string>>|null */
    private static ?array $pluginsByContract = null;

    /** @var array<string, list<class-string>> */
    private static array $classesByTag = [];

    /** Which application, module paths and namespaces the memo answers for. */
    private static string $scope = '';

    /**
     * The declared plugins, followed by the attribute members not already among
     * them.
     *
     * @param list<class-string> $declared
     * @param string $scope changes when the application root, module paths or namespaces do
     * @param Closure(): Members $load called once per scope
     *
     * @return list<class-string>
     */
    public static function pluginsOf(string $contract, array $declared, string $scope, Closure $load): array
    {
        self::load($scope, $load);

        $plugins = $declared;

        foreach (self::$pluginsByContract[$contract] ?? [] as $member) {
            if (!in_array($member, $plugins, true)) {
                $plugins[] = $member;
            }
        }

        return $plugins;
    }

    /**
     * @param Closure(): Members $load called once per scope
     *
     * @return list<class-string>
     */
    public static function classesTagged(string $tag, string $scope, Closure $load): array
    {
        self::load($scope, $load);

        return self::$classesByTag[$tag] ?? [];
    }

    public static function resetCache(): void
    {
        self::$pluginsByContract = null;
        self::$classesByTag = [];
        self::$scope = '';
    }

    /**
     * @param Closure(): Members $load
     */
    private static function load(string $scope, Closure $load): void
    {
        if (self::$pluginsByContract !== null && self::$scope === $scope) {
            return;
        }

        // Assigned only once the load returned, so a scan that throws is
        // thrown again on the next ask instead of leaving every stack with
        // its declared members only.
        $members = $load();

        $byContract = [];
        foreach ($members->plugins as $member) {
            $byContract[$member->contract][] = $member->plugin;
        }

        $byTag = [];
        foreach ($members->tags as $member) {
            $byTag[$member->tag][] = $member->class;
        }

        self::$pluginsByContract = $byContract;
        self::$classesByTag = $byTag;
        self::$scope = $scope;
    }
}
