<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\GlobLiteral;
use Gacela\Framework\Config\PathFinder;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GlobLiteralTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function literalDirectories(): iterable
    {
        yield 'brackets' => ['cache[v1]'];
        yield 'closing bracket first' => ['cache]v1['];
        yield 'braces' => ['cache{v1}'];
        yield 'brace alternatives' => ['cache{eu,us}'];
        yield 'mixed' => ['cache[]{eu,us}'];
        if (PHP_OS_FAMILY !== 'Windows') {
            yield 'wildcards' => ['cache*?'];
            yield 'backslash and wildcard' => ['cache\\*'];
            yield 'all metacharacters' => ['cache[]{}*?'];
        }
    }

    #[DataProvider('literalDirectories')]
    public function test_only_the_literal_directory_matches(string $name): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-glob-' . uniqid('', true);
        $directory = $root . DIRECTORY_SEPARATOR . $name;
        mkdir($directory, 0777, true);
        $neighbour = $root . DIRECTORY_SEPARATOR . 'cache[neighbour]';
        mkdir($neighbour);
        file_put_contents($neighbour . DIRECTORY_SEPARATOR . 'app.php', '<?php return [];');
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'app.php', '<?php return [];');
        try {
            $matches = (new PathFinder())->matchingPattern(GlobLiteral::escape($directory) . '/*.php');

            self::assertSame([realpath($directory . DIRECTORY_SEPARATOR . 'app.php')], array_map(realpath(...), $matches));
        } finally {
            DirectoryUtil::removeDir($root);
        }
    }
}
