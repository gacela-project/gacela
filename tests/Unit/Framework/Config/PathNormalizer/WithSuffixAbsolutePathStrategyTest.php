<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\PathNormalizer;

use Gacela\Framework\Config\PathNormalizer\WithSuffixAbsolutePathStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WithSuffixAbsolutePathStrategyTest extends TestCase
{
    public function test_file_without_extension_neither_suffix_then_empty_string(): void
    {
        $strategy = new WithSuffixAbsolutePathStrategy('/app/root/');
        $relativePath = '/file-name';

        self::assertSame('', $strategy->generateAbsolutePath($relativePath));
    }

    public function test_file_without_extension_but_suffix(): void
    {
        $strategy = new WithSuffixAbsolutePathStrategy('/app/root/', 'suffix');
        $relativePath = '/file-name';

        self::assertSame(
            '/app/root/file-name-suffix',
            $strategy->generateAbsolutePath($relativePath),
        );
    }

    public function test_file_with_extension_but_no_suffix_then_empty_string(): void
    {
        $strategy = new WithSuffixAbsolutePathStrategy('/app/root/');
        $relativePath = '/file-name.ext';

        self::assertSame('', $strategy->generateAbsolutePath($relativePath));
    }

    public function test_file_with_extension_and_suffix(): void
    {
        $strategy = new WithSuffixAbsolutePathStrategy('/app/root/', 'suffix');
        $relativePath = '/file-name.ext';

        self::assertSame(
            '/app/root/file-name-suffix.ext',
            $strategy->generateAbsolutePath($relativePath),
        );
    }

    #[DataProvider('providePathsWithDotsBeforeTheFileName')]
    public function test_suffix_goes_into_the_file_name_not_a_dotted_directory(string $relativePath, string $expected): void
    {
        $strategy = new WithSuffixAbsolutePathStrategy('/app', 'prod');

        self::assertSame($expected, $strategy->generateAbsolutePath($relativePath));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePathsWithDotsBeforeTheFileName(): iterable
    {
        yield 'current directory prefix' => ['./config/*.php', '/app/./config/*-prod.php'];
        yield 'dotted directory' => ['config.d/*.php', '/app/config.d/*-prod.php'];
        yield 'hidden directory' => ['.config/app.php', '/app/.config/app-prod.php'];
        yield 'versioned directory' => ['etc/v1.2/app.php', '/app/etc/v1.2/app-prod.php'];
        yield 'windows separator' => ['etc\\v1.2\\app.php', '/app/etc\\v1.2\\app-prod.php'];
        yield 'dotted directory, file without extension' => ['config.d/app', '/app/config.d/app-prod'];
    }
}
