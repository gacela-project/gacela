<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\AllAppModules;

use Gacela\Console\Domain\AllAppModules\ExcludedDirectories;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function dirname;
use function file_put_contents;
use function mkdir;
use function sort;
use function sys_get_temp_dir;
use function uniqid;

final class ExcludedDirectoriesTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/gacela-scan-' . uniqid('', true);
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        DirectoryUtil::removeDir($this->tempDir);
    }

    public function test_vendor_and_node_modules_are_excluded(): void
    {
        $excluded = new ExcludedDirectories();

        self::assertTrue($excluded->isExcluded('vendor'));
        self::assertTrue($excluded->isExcluded('node_modules'));
    }

    public function test_every_hidden_directory_is_excluded(): void
    {
        $excluded = new ExcludedDirectories();

        self::assertTrue($excluded->isExcluded('.git'));
        self::assertTrue($excluded->isExcluded('.idea'));
        self::assertTrue($excluded->isExcluded('.phpunit.cache'));
    }

    /**
     * Guessing that a project's `build/` or `data/` holds no modules is how
     * discovery starts silently missing them.
     */
    public function test_ordinary_source_directories_are_allowed(): void
    {
        $excluded = new ExcludedDirectories();

        self::assertFalse($excluded->isExcluded('src'));
        self::assertFalse($excluded->isExcluded('build'));
        self::assertFalse($excluded->isExcluded('data'));
        self::assertFalse($excluded->isExcluded('vendors'));
    }

    /**
     * The name is only consulted for something that can be descended into, so a
     * *file* called `vendor` is still scanned.
     */
    public function test_a_file_named_like_an_excluded_directory_is_still_yielded(): void
    {
        $this->writeFile('src/Real/RealFacade.php');
        file_put_contents($this->tempDir . '/vendor', '<?php');

        $found = [];
        foreach ($this->prunedIteratorForTempDir() as $fileInfo) {
            $found[] = $fileInfo->getFilename();
        }

        sort($found);

        self::assertSame(['RealFacade.php', 'vendor'], $found);
    }

    /**
     * The point of pruning rather than filtering after the fact: an excluded
     * directory is never descended into, so nothing inside it is ever yielded.
     * A post-hoc filter would still pay for walking every one of those files.
     */
    public function test_nothing_inside_an_excluded_directory_is_ever_yielded(): void
    {
        $this->writeFile('src/Real/RealFacade.php');
        $this->writeFile('vendor/pkg/deep/nested/VendorFacade.php');
        $this->writeFile('node_modules/pkg/NodeFacade.php');
        $this->writeFile('.git/objects/HiddenFacade.php');

        $found = [];
        foreach ($this->prunedIteratorForTempDir() as $fileInfo) {
            $found[] = $fileInfo->getFilename();
        }

        sort($found);

        self::assertSame(['RealFacade.php'], $found);
    }

    /**
     * @return RecursiveIteratorIterator<RecursiveCallbackFilterIterator>
     */
    private function prunedIteratorForTempDir(): RecursiveIteratorIterator
    {
        $excluded = new ExcludedDirectories();

        return new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                static fn (mixed $current, mixed $key, RecursiveDirectoryIterator $iterator): bool => !$iterator->hasChildren() || !$excluded->isExcluded($iterator->getFilename()),
            ),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
    }

    private function writeFile(string $relativePath): void
    {
        $path = $this->tempDir . '/' . $relativePath;
        @mkdir(dirname($path), 0o777, true);
        file_put_contents($path, '<?php');
    }

}
