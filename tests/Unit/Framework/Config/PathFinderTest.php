<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Config\PathFinder;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class PathFinderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/gacela_path_finder_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        PathFinder::resetCache();
    }

    protected function tearDown(): void
    {
        DirectoryUtil::removeDir($this->tempDir);
        PathFinder::resetCache();
    }

    public function test_an_empty_pattern_matches_nothing(): void
    {
        self::assertSame([], (new PathFinder())->matchingPattern(''));
    }

    public function test_an_empty_pattern_never_reaches_the_cache(): void
    {
        (new PathFinder())->matchingPattern('');

        $cache = new ReflectionProperty(PathFinder::class, 'cache');

        self::assertSame([], $cache->getValue());
    }

    public function test_the_second_lookup_of_the_same_pattern_is_served_from_the_cache(): void
    {
        $pattern = $this->tempDir . '/*.php';
        file_put_contents($this->tempDir . '/first.php', '<?php');

        $pathFinder = new PathFinder();
        $firstResult = $pathFinder->matchingPattern($pattern);

        file_put_contents($this->tempDir . '/second.php', '<?php');

        self::assertSame($firstResult, $pathFinder->matchingPattern($pattern));
        self::assertSame([$this->tempDir . '/first.php'], $firstResult);
    }

    public function test_resetting_the_cache_re_scans_the_filesystem(): void
    {
        $pattern = $this->tempDir . '/*.php';
        file_put_contents($this->tempDir . '/first.php', '<?php');

        $pathFinder = new PathFinder();
        $pathFinder->matchingPattern($pattern);

        file_put_contents($this->tempDir . '/second.php', '<?php');
        PathFinder::resetCache();

        self::assertSame(
            [$this->tempDir . '/first.php', $this->tempDir . '/second.php'],
            $pathFinder->matchingPattern($pattern),
        );
    }
}
