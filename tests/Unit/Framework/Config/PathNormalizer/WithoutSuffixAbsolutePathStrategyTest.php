<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\PathNormalizer;

use Gacela\Framework\Config\PathFinder;
use Gacela\Framework\Config\PathNormalizer\WithoutSuffixAbsolutePathStrategy;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class WithoutSuffixAbsolutePathStrategyTest extends TestCase
{
    public function test_a_metacharacter_in_the_app_root_is_matched_literally(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-root-[v1]-' . uniqid('', true);
        $file = $root . DIRECTORY_SEPARATOR . 'app.php';
        mkdir($root);
        file_put_contents($file, '<?php return [];');
        try {
            $pattern = (new WithoutSuffixAbsolutePathStrategy($root))->generateAbsolutePath('*.php');

            self::assertSame([realpath($file)], array_map(realpath(...), (new PathFinder())->matchingPattern($pattern)));
        } finally {
            DirectoryUtil::removeDir($root);
        }
    }

    public function test_file_without_extension(): void
    {
        $strategy = new WithoutSuffixAbsolutePathStrategy('/app/root/');
        $relativePath = '/file-name';

        self::assertSame(
            '/app/root/file-name',
            $strategy->generateAbsolutePath($relativePath),
        );
    }

    public function test_file_with_extension(): void
    {
        $strategy = new WithoutSuffixAbsolutePathStrategy('/app/root/');
        $relativePath = '/file-name.ext';

        self::assertSame(
            '/app/root/file-name.ext',
            $strategy->generateAbsolutePath($relativePath),
        );
    }
}
