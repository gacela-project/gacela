<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\PathNormalizer;

use Gacela\Framework\Config\PathNormalizer\SuffixAbsolutePathStrategy;
use PHPUnit\Framework\TestCase;

final class SuffixAbsolutePathStrategyTest extends TestCase
{
    public function test_file_without_extension_neither_suffix_then_empty_string(): void
    {
        $strategy = new SuffixAbsolutePathStrategy('/app/root/');
        $relativePath = '/file-name';

        self::assertSame('', $strategy->generateAbsolutePath($relativePath));
    }

    public function test_file_without_extension_but_suffix(): void
    {
        $strategy = new SuffixAbsolutePathStrategy('/app/root/', 'suffix');
        $relativePath = '/file-name';

        self::assertSame(
            '/app/root/file-name-suffix',
            $strategy->generateAbsolutePath($relativePath)
        );
    }

    public function test_file_with_extension_but_no_suffix_then_empty_string(): void
    {
        $strategy = new SuffixAbsolutePathStrategy('/app/root/');
        $relativePath = '/file-name.ext';

        self::assertSame('', $strategy->generateAbsolutePath($relativePath));
    }

    public function test_file_with_extension_and_suffix(): void
    {
        $strategy = new SuffixAbsolutePathStrategy('/app/root/', 'suffix');
        $relativePath = '/file-name.ext';

        self::assertSame(
            '/app/root/file-name-suffix.ext',
            $strategy->generateAbsolutePath($relativePath)
        );
    }
}
