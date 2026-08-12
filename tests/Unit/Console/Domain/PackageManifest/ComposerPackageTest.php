<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\PackageManifest;

use Gacela\Console\Domain\PackageManifest\ComposerPackage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ComposerPackageTest extends TestCase
{
    /**
     * A manifest without a publishable name has no standalone install to break,
     * so there is nothing for the check to be about.
     *
     * @param array<array-key, mixed> $decoded
     */
    #[DataProvider('unpublishableManifestProvider')]
    public function test_a_manifest_that_cannot_be_published_is_skipped(array $decoded): void
    {
        self::assertNull(ComposerPackage::fromDecodedJson($decoded, '/repo/composer.json', '/repo'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function unpublishableManifestProvider(): iterable
    {
        yield 'no name' => [['require' => ['acme/thing' => '^1.0']]];
        yield 'empty name' => [['name' => '']];
        yield 'name is not a string' => [['name' => 42]];
    }

    public function test_it_reads_the_name_and_where_it_lives(): void
    {
        $package = $this->package(['name' => 'acme/thing']);

        self::assertSame('acme/thing', $package->name);
        self::assertSame('/repo/composer.json', $package->manifestPath);
        self::assertSame('/repo', $package->rootDir);
    }

    #[DataProvider('declaringSectionProvider')]
    public function test_a_package_named_in_any_section_counts_as_declared(string $section): void
    {
        $package = $this->package(['name' => 'acme/thing', $section => ['acme/other' => '^1.0']]);

        self::assertTrue($package->declares('acme/other'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function declaringSectionProvider(): iterable
    {
        yield 'require' => ['require'];
        yield 'require-dev' => ['require-dev'];
        yield 'suggest' => ['suggest'];
    }

    public function test_a_package_named_nowhere_is_not_declared(): void
    {
        $package = $this->package(['name' => 'acme/thing', 'require' => ['acme/other' => '^1.0']]);

        self::assertFalse($package->declares('acme/missing'));
    }

    public function test_the_sections_are_read_separately(): void
    {
        $package = $this->package([
            'name' => 'acme/thing',
            'require' => ['acme/runtime' => '^1.0'],
            'require-dev' => ['acme/dev' => '^1.0'],
            'suggest' => ['acme/optional' => 'why'],
        ]);

        self::assertSame(['acme/runtime'], $package->required);
        self::assertSame(['acme/dev'], $package->requiredForDev);
        self::assertSame(['acme/optional'], $package->suggested);
    }

    public function test_autoload_prefixes_come_from_psr_4_and_psr_0(): void
    {
        $package = $this->package([
            'name' => 'acme/thing',
            'autoload' => ['psr-4' => ['Acme\\' => 'src'], 'psr-0' => ['Acme_' => 'lib']],
        ]);

        self::assertSame(['Acme\\' => 'src', 'Acme_' => 'lib'], $package->autoloadPrefixes);
    }

    /**
     * `autoload-dev` is not installed for a consumer, so an import reachable
     * only from it cannot break a standalone install.
     */
    public function test_autoload_dev_is_not_a_prefix_of_the_package(): void
    {
        $package = $this->package([
            'name' => 'acme/thing',
            'autoload' => ['psr-4' => ['Acme\\' => 'src']],
            'autoload-dev' => ['psr-4' => ['AcmeTest\\' => 'tests']],
        ]);

        self::assertSame(['Acme\\' => 'src'], $package->autoloadPrefixes);
    }

    public function test_a_prefix_mapped_to_several_directories_keeps_the_first(): void
    {
        $package = $this->package([
            'name' => 'acme/thing',
            'autoload' => ['psr-4' => ['Acme\\' => 'src'], 'psr-0' => ['Acme\\' => 'legacy']],
        ]);

        self::assertSame(['Acme\\' => 'src'], $package->autoloadPrefixes);
    }

    public function test_an_autoload_section_that_is_not_an_object_yields_no_prefixes(): void
    {
        $package = $this->package(['name' => 'acme/thing', 'autoload' => 'nonsense']);

        self::assertSame([], $package->autoloadPrefixes);
    }

    public function test_a_standard_inside_autoload_that_is_not_an_object_is_skipped(): void
    {
        $package = $this->package([
            'name' => 'acme/thing',
            'autoload' => ['psr-4' => 'nonsense', 'psr-0' => ['Acme_' => 'lib']],
        ]);

        self::assertSame(['Acme_' => 'lib'], $package->autoloadPrefixes);
    }

    /**
     * An empty prefix means "every namespace", and a numeric key is not a
     * namespace at all; neither names something a package provides.
     */
    public function test_a_prefix_that_is_empty_or_not_a_string_is_skipped(): void
    {
        $package = $this->package([
            'name' => 'acme/thing',
            'autoload' => ['psr-4' => ['' => 'src', 0 => 'lib', 'Acme\\' => 'app']],
        ]);

        self::assertSame(['Acme\\' => 'app'], $package->autoloadPrefixes);
    }

    public function test_a_manifest_declaring_nothing_reads_as_empty(): void
    {
        $package = $this->package(['name' => 'acme/thing']);

        self::assertSame([], $package->autoloadPrefixes);
        self::assertSame([], $package->required);
        self::assertSame([], $package->requiredForDev);
        self::assertSame([], $package->suggested);
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private function package(array $decoded): ComposerPackage
    {
        $package = ComposerPackage::fromDecodedJson($decoded, '/repo/composer.json', '/repo');
        self::assertInstanceOf(ComposerPackage::class, $package);

        return $package;
    }
}
