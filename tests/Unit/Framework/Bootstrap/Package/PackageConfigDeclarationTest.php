<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Package;

use Gacela\Framework\Bootstrap\Package\PackageConfigDeclaration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shape the resolved declaration list is remembered in.
 *
 * `fromArray()` reads a row back out of the cache file, which is a file on disk
 * that nothing stops anyone from editing -- so every field is checked, and a row
 * that is not three strings is not a declaration.
 */
final class PackageConfigDeclarationTest extends TestCase
{
    public function test_a_declaration_survives_the_round_trip_through_an_array(): void
    {
        $declaration = new PackageConfigDeclaration(
            'acme/audit',
            'config/gacela.php',
            '/app/vendor/acme/audit/config/gacela.php',
        );

        self::assertEquals($declaration, PackageConfigDeclaration::fromArray($declaration->toArray()));
    }

    public function test_the_declared_path_and_the_resolved_one_are_both_kept(): void
    {
        $declaration = PackageConfigDeclaration::fromArray([
            'name' => 'acme/audit',
            'declaredPath' => './config/gacela.php',
            'configFile' => '/app/vendor/acme/audit/config/gacela.php',
        ]);

        self::assertInstanceOf(PackageConfigDeclaration::class, $declaration);
        self::assertSame('acme/audit', $declaration->name);
        self::assertSame('./config/gacela.php', $declaration->declaredPath);
        self::assertSame('/app/vendor/acme/audit/config/gacela.php', $declaration->configFile);
    }

    /**
     * One field at a time, because each of the three is checked for itself: a
     * row carrying a usable name and a usable path is still not a declaration
     * when the third field is not a string, and the answer must be null rather
     * than a half-built declaration.
     *
     * @param array<array-key, mixed> $row
     */
    #[DataProvider('unusableRows')]
    public function test_a_row_that_is_not_three_strings_is_not_a_declaration(array $row): void
    {
        self::assertNull(PackageConfigDeclaration::fromArray($row));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function unusableRows(): iterable
    {
        yield 'the name is not a string' => [[
            'name' => 42,
            'declaredPath' => 'config/gacela.php',
            'configFile' => '/app/vendor/acme/audit/config/gacela.php',
        ]];

        yield 'the declared path is not a string' => [[
            'name' => 'acme/audit',
            'declaredPath' => ['config/gacela.php'],
            'configFile' => '/app/vendor/acme/audit/config/gacela.php',
        ]];

        yield 'the resolved file is not a string' => [[
            'name' => 'acme/audit',
            'declaredPath' => 'config/gacela.php',
            'configFile' => null,
        ]];

        yield 'nothing at all' => [[]];
    }
}
