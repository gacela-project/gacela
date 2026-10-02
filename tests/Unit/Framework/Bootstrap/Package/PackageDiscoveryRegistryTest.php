<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Package;

use Gacela\Framework\Bootstrap\Package\DiscoveredPackage;
use Gacela\Framework\Bootstrap\Package\PackageConfigDeclaration;
use Gacela\Framework\Bootstrap\Package\PackageContribution;
use Gacela\Framework\Bootstrap\Package\PackageDiscoveryRegistry;
use Gacela\Framework\Bootstrap\Package\RefusedPackage;
use PHPUnit\Framework\TestCase;

final class PackageDiscoveryRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        PackageDiscoveryRegistry::reset();
    }

    public function test_the_sources_of_packages_sharing_a_namespace_are_joined(): void
    {
        PackageDiscoveryRegistry::record($this->discovered('acme/one', ['Acme\\' => ['/one/src']]));
        PackageDiscoveryRegistry::record($this->discovered('acme/two', ['Acme\\' => ['/two/src'], 'Two\\' => ['/two/lib']]));

        self::assertSame(
            ['Acme\\' => ['/one/src', '/two/src'], 'Two\\' => ['/two/lib']],
            PackageDiscoveryRegistry::sources(),
        );
    }

    public function test_a_refused_package_gives_its_directories_to_exclude_not_to_read(): void
    {
        $declaration = new PackageConfigDeclaration('acme/audit', 'config/gacela.php', '/audit/config/gacela.php', [
            'Acme\\Audit\\' => ['/audit/src'],
            'Acme\\Audit\\Extra\\' => ['/audit/lib', '/audit/more'],
        ]);
        PackageDiscoveryRegistry::refuse(RefusedPackage::optedOut($declaration));

        self::assertSame([], PackageDiscoveryRegistry::sources());
        self::assertSame(['/audit/src', '/audit/lib', '/audit/more'], PackageDiscoveryRegistry::refusedDirectories());
    }

    /**
     * @param array<string, list<string>> $sources
     */
    private function discovered(string $name, array $sources): DiscoveredPackage
    {
        return new DiscoveredPackage($name, '/' . $name . '/config/gacela.php', 1, PackageContribution::fromArray([]), $sources);
    }
}
