<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\DtoGenerate;

use Gacela\Console\Domain\DtoGenerate\GeneratedClassPath;
use Gacela\Console\Domain\PackageManifest\ComposerPackage;
use PHPUnit\Framework\TestCase;

use function implode;

final class GeneratedClassPathTest extends TestCase
{
    public function test_a_class_lands_under_the_prefix_directory(): void
    {
        $path = new GeneratedClassPath(['App\\' => 'src'], '/repo');

        self::assertSame(
            $this->joined('/repo', 'src', 'Checkout', 'Order.php'),
            $path->fileFor('App\Checkout\Order'),
        );
    }

    /**
     * Composer resolves by longest prefix, and so must this: a project with
     * both `App\` and `App\Generated\` means the second for a class under it.
     */
    public function test_the_longest_matching_prefix_wins(): void
    {
        $path = new GeneratedClassPath(['App\\' => 'src', 'App\Generated\\' => 'build/dto'], '/repo');

        self::assertSame(
            $this->joined('/repo', 'build/dto', 'Order.php'),
            $path->fileFor('App\Generated\Order'),
        );
    }

    public function test_a_trailing_separator_on_the_directory_is_tolerated(): void
    {
        $path = new GeneratedClassPath(['App\\' => 'src/'], '/repo');

        self::assertSame(
            $this->joined('/repo', 'src', 'Checkout', 'Order.php'),
            $path->fileFor('App\Checkout\Order'),
        );
    }

    /**
     * Nowhere to write it, and saying so beats writing it where nothing loads it.
     */
    public function test_a_class_no_prefix_covers_has_no_file(): void
    {
        $path = new GeneratedClassPath(['App\\' => 'src'], '/repo');

        self::assertNull($path->fileFor('Other\Checkout\Order'));
    }

    /**
     * A prefix that does not match is skipped, not a reason to stop looking:
     * the prefixes after it may still cover the class.
     */
    public function test_a_non_matching_prefix_does_not_end_the_search(): void
    {
        $path = new GeneratedClassPath(['Other\\' => 'other', 'App\\' => 'src'], '/repo');

        self::assertSame(
            $this->joined('/repo', 'src', 'Order.php'),
            $path->fileFor('App\Order'),
        );
    }

    public function test_without_any_prefix_nothing_can_be_placed(): void
    {
        self::assertNull((new GeneratedClassPath([], '/repo'))->fileFor('App\Order'));
    }

    public function test_a_list_valued_manifest_prefix_uses_its_first_directory(): void
    {
        $prefixes = ComposerPackage::psr4PrefixesOf(['autoload' => ['psr-4' => ['App\\' => ['src', 'lib']]]]);

        self::assertSame(
            $this->joined('/repo', 'src', 'Checkout', 'Order.php'),
            (new GeneratedClassPath($prefixes, '/repo'))->fileFor('App\Checkout\Order'),
        );
    }

    public function test_an_empty_directory_cannot_place_a_class(): void
    {
        self::assertNull((new GeneratedClassPath(['App\\' => ''], '/repo'))->fileFor('App\Checkout\Order'));
    }

    private function joined(string $root, string ...$segments): string
    {
        return $root . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, array_map(
                static fn (string $s): string => str_replace('/', DIRECTORY_SEPARATOR, $s),
                $segments,
            ));
    }
}
