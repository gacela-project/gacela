<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\IdeMeta;

use Gacela\Console\Domain\IdeMeta\IdeMetadataPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function implode;

final class IdeMetadataPathTest extends TestCase
{
    public function test_the_directory_hangs_off_the_application_root(): void
    {
        self::assertSame(
            $this->joined('app', IdeMetadataPath::DIRECTORY),
            IdeMetadataPath::directoryIn('app'),
        );
    }

    public function test_the_file_sits_inside_that_directory(): void
    {
        self::assertSame(
            $this->joined('app', IdeMetadataPath::DIRECTORY, IdeMetadataPath::FILENAME),
            IdeMetadataPath::fileIn('app'),
        );
    }

    /**
     * An application root is configuration, so it may or may not end in a
     * separator -- and on Windows either separator is one.
     */
    #[DataProvider('trailingSeparatorProvider')]
    public function test_a_root_that_already_ends_in_a_separator_produces_no_doubled_one(string $appRootDir): void
    {
        self::assertSame(
            $this->joined('app', IdeMetadataPath::DIRECTORY, IdeMetadataPath::FILENAME),
            IdeMetadataPath::fileIn($appRootDir),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function trailingSeparatorProvider(): iterable
    {
        yield 'forward slash' => ['app/'];
        yield 'backslash' => ['app\\'];
        yield 'several' => ['app//'];
    }

    private function joined(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
