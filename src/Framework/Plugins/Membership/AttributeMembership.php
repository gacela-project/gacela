<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use Closure;
use LogicException;

use function implode;
use function in_array;

/**
 * The `#[Plugin]`, `#[Tag]` and `#[AsListener]` members, loaded once per process.
 *
 * Asked only when a declared stack is first resolved, a tag first read or an
 * application event first dispatched, so an application that does none of
 * these never loads them.
 *
 * @internal
 */
final class AttributeMembership
{
    /** @var array<string, list<class-string>>|null */
    private static ?array $pluginsByContract = null;

    /** @var array<string, list<class-string>> */
    private static array $classesByTag = [];

    /** @var list<ListenerMember> */
    private static array $listeners = [];

    /** @var list<string> */
    private static array $listenerProblems = [];

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

    /**
     * @param Closure(): Members $load called once per scope
     *
     * @throws LogicException naming the `#[AsListener]` methods that cannot be registered
     *
     * @return list<ListenerMember>
     */
    public static function listeners(string $scope, Closure $load): array
    {
        self::load($scope, $load);

        // Thrown here, not by the scan: a bad listener must not break the
        // plugin stacks and tags read from the same scan.
        if (self::$listenerProblems !== []) {
            throw new LogicException(implode("\n", self::$listenerProblems));
        }

        return self::$listeners;
    }

    public static function resetCache(): void
    {
        self::$pluginsByContract = null;
        self::$classesByTag = [];
        self::$listeners = [];
        self::$listenerProblems = [];
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
        self::$listeners = $members->listeners;
        self::$listenerProblems = $members->problems;
        self::$scope = $scope;
    }
}
