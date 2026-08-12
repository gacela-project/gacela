<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\PackageManifest;

use Gacela\Console\Domain\PackageManifest\ComposerPackage;
use Gacela\Console\Domain\PackageManifest\NamespacePackageMap;
use PHPUnit\Framework\TestCase;

final class NamespacePackageMapTest extends TestCase
{
    public function test_a_namespace_nothing_claims_has_no_provider(): void
    {
        $map = NamespacePackageMap::from([], []);

        self::assertSame([], $map->packagesProviding('Nobody\Owns\This'));
    }

    public function test_an_installed_package_provides_its_own_prefix(): void
    {
        $map = NamespacePackageMap::from([], [
            $this->installed('psr/container', ['Psr\Container\\']),
        ]);

        self::assertSame(['psr/container'], $map->packagesProviding(\Psr\Container\ContainerInterface::class));
    }

    /**
     * A monorepo publishes `Gacela\` and `Gacela\LaravelBridge\` as separate
     * packages. Resolving by the shorter prefix would name the wrong package and
     * send a reader to fix the wrong manifest.
     */
    public function test_the_longest_matching_prefix_wins(): void
    {
        $map = NamespacePackageMap::from([], [
            $this->installed('acme/framework', ['Acme\\']),
            $this->installed('acme/bridge', ['Acme\Bridge\\']),
        ]);

        self::assertSame(['acme/bridge'], $map->packagesProviding('Acme\Bridge\Thing'));
        self::assertSame(['acme/framework'], $map->packagesProviding('Acme\Framework\Thing'));
    }

    /**
     * Laravel publishes `Illuminate\Support\` from two packages. Picking one
     * arbitrarily reports a requirement as missing that the manifest already
     * has, so every claimant is kept.
     */
    public function test_a_prefix_two_packages_claim_reports_both(): void
    {
        $map = NamespacePackageMap::from([], [
            $this->installed('illuminate/support', ['Illuminate\Support\\']),
            $this->installed('illuminate/collections', ['Illuminate\Support\\']),
        ]);

        self::assertSame(
            ['illuminate/collections', 'illuminate/support'],
            $map->packagesProviding(\Illuminate\Support\ServiceProvider::class),
        );
    }

    public function test_one_package_claiming_a_prefix_twice_is_listed_once(): void
    {
        $map = NamespacePackageMap::from([], [
            ['name' => 'acme/thing', 'autoload' => ['psr-4' => ['Acme\\' => 'src'], 'psr-0' => ['Acme\\' => 'legacy']]],
        ]);

        self::assertSame(['acme/thing'], $map->packagesProviding('Acme\Thing'));
    }

    public function test_psr_0_prefixes_are_read_too(): void
    {
        $map = NamespacePackageMap::from([], [
            ['name' => 'acme/legacy', 'autoload' => ['psr-0' => ['Acme_' => 'lib']]],
        ]);

        self::assertSame(['acme/legacy'], $map->packagesProviding('Acme_Old_Thing'));
    }

    public function test_a_local_package_provides_its_prefix(): void
    {
        $package = ComposerPackage::fromDecodedJson(
            ['name' => 'acme/local', 'autoload' => ['psr-4' => ['Acme\Local\\' => 'src']]],
            '/repo/composer.json',
            '/repo',
        );
        self::assertInstanceOf(ComposerPackage::class, $package);

        $map = NamespacePackageMap::from([$package], []);

        self::assertSame(['acme/local'], $map->packagesProviding('Acme\Local\Thing'));
    }

    public function test_an_entry_without_a_name_is_ignored(): void
    {
        // The malformed entries come first, so that skipping them is not the
        // same as stopping at them: the readable entry after has to be found.
        $map = NamespacePackageMap::from([], [
            'not an object',
            ['autoload' => ['psr-4' => ['Nameless\\' => 'src']]],
            $this->installed('acme/readable', ['Acme\\']),
        ]);

        self::assertSame([], $map->packagesProviding('Nameless\Thing'));
        self::assertSame(['acme/readable'], $map->packagesProviding('Acme\Thing'));
    }

    public function test_an_entry_without_autoload_claims_nothing(): void
    {
        $map = NamespacePackageMap::from([], [
            ['name' => 'phpstan/phpstan'],
        ]);

        self::assertSame([], $map->packagesProviding(\PHPStan\Analyser\Scope::class));
    }

    /**
     * @param list<string> $prefixes
     *
     * @return array<string, mixed>
     */
    private function installed(string $name, array $prefixes): array
    {
        $psr4 = [];

        foreach ($prefixes as $prefix) {
            $psr4[$prefix] = 'src';
        }

        return ['name' => $name, 'autoload' => ['psr-4' => $psr4]];
    }
}
