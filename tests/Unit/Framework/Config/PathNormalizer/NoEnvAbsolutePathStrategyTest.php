<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\PathNormalizer;

use Gacela\Framework\Config\PathNormalizer\NoEnvAbsolutePathStrategy;
use PHPUnit\Framework\TestCase;

final class NoEnvAbsolutePathStrategyTest extends TestCase
{
    public function test_file_without_extension(): void
    {
        $strategy = new NoEnvAbsolutePathStrategy('/app/root/');
        $relativePath = '/file-name';

        self::assertSame(
            '/app/root/file-name',
            $strategy->generateAbsolutePath($relativePath)
        );
    }

    public function test_file_with_extension(): void
    {
        $strategy = new NoEnvAbsolutePathStrategy('/app/root/');
        $relativePath = '/file-name.ext';

        self::assertSame(
            '/app/root/file-name.ext',
            $strategy->generateAbsolutePath($relativePath)
        );
    }
}
