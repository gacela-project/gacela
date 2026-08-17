<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

/**
 * What the last discovery did, for whoever asks after the boot.
 *
 * A feature that runs a package's code during bootstrap has to be inspectable
 * afterwards, and by then the setups it read are gone: `debug:container` and the
 * `doctor` check both run against an already-booted application. This is where
 * the answer waits for them.
 *
 * Static, like {@see \Gacela\Framework\Health\HealthCheckRegistry}, for the same
 * reason: discovery runs below the container, so there is nothing to inject it
 * into and nothing to inject into it.
 *
 * Reset by {@see PackageDiscovery} immediately before it discovers, not by
 * `Gacela::bootstrap()`. The merged configuration is memoized, and a second
 * bootstrap that hits the memo does no discovery at all -- clearing this then
 * would leave the registry claiming nothing was discovered for a configuration
 * that has packages in it.
 *
 * @internal
 */
final class PackageDiscoveryRegistry
{
    /** @var list<DiscoveredPackage> */
    private static array $discovered = [];

    /** @var list<RefusedPackage> */
    private static array $refused = [];

    private static bool $disabled = false;

    public static function reset(): void
    {
        self::$discovered = [];
        self::$refused = [];
        self::$disabled = false;
    }

    /**
     * `dontDiscover(['*'])`: no declaration was read, so there are no names to
     * list as refused -- only the fact that nothing was looked at.
     */
    public static function disable(): void
    {
        self::$disabled = true;
    }

    public static function isDisabled(): bool
    {
        return self::$disabled;
    }

    public static function record(DiscoveredPackage $package): void
    {
        self::$discovered[] = $package;
    }

    public static function refuse(RefusedPackage $package): void
    {
        self::$refused[] = $package;
    }

    /**
     * In merge order.
     *
     * @return list<DiscoveredPackage>
     */
    public static function discovered(): array
    {
        return self::$discovered;
    }

    /**
     * @return list<RefusedPackage>
     */
    public static function refused(): array
    {
        return self::$refused;
    }
}
