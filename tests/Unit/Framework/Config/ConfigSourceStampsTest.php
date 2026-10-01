<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Config\ConfigSourceStamps;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function rename;
use function rmdir;
use function stat;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;

final class ConfigSourceStampsTest extends TestCase
{
    private string $dir;

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-stamps-' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
    }

    public function test_an_unchanged_file_is_current(): void
    {
        $file = $this->write('a.php', 'one', 1_000_000);

        self::assertTrue(ConfigSourceStamps::areCurrent(ConfigSourceStamps::of([$file])));
    }

    public function test_a_change_of_mtime_alone_is_seen(): void
    {
        $file = $this->write('a.php', 'one', 1_000_000);
        $stamps = ConfigSourceStamps::of([$file]);

        touch($file, 1_000_001);

        self::assertFalse(ConfigSourceStamps::areCurrent($stamps));
    }

    public function test_a_change_of_size_alone_is_seen(): void
    {
        $file = $this->write('a.php', 'one', 1_000_000);
        $stamps = ConfigSourceStamps::of([$file]);

        $this->write('a.php', 'three', 1_000_000);

        self::assertFalse(ConfigSourceStamps::areCurrent($stamps));
    }

    /**
     * How an editor saves: a new file renamed over the old one, here with the
     * same size and second, so only the inode tells them apart.
     */
    public function test_a_file_renamed_over_the_source_is_seen(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('NTFS reports no inode to stat()');
        }

        $file = $this->write('a.php', 'one', 1_000_000);
        $replacement = $this->write('a.php.new', 'two', 1_000_000);
        $stamps = ConfigSourceStamps::of([$file]);

        rename($replacement, $file);

        self::assertFalse(ConfigSourceStamps::areCurrent($stamps));
    }

    /**
     * PHP keeps the last stat it made, so without clearing it a check in the
     * same process reads the answer from before the write.
     */
    public function test_a_write_in_the_same_process_is_seen(): void
    {
        $file = $this->write('a.php', 'one', 1_000_000);
        $stamps = ConfigSourceStamps::of([$file]);
        stat($file);

        file_put_contents($file, 'longer content');

        self::assertFalse(ConfigSourceStamps::areCurrent($stamps));
    }

    public function test_a_path_that_appears_later_is_seen(): void
    {
        $file = $this->dir . DIRECTORY_SEPARATOR . 'later.php';
        $stamps = ConfigSourceStamps::of([$file]);

        $this->write('later.php', 'here now', 1_000_000);

        self::assertFalse(ConfigSourceStamps::areCurrent($stamps));
    }

    public function test_a_stamp_from_an_earlier_second_cannot_miss_a_change(): void
    {
        self::assertFalse(ConfigSourceStamps::couldMissAChange(['/a' => '999:10:1'], 1_000));
    }

    public function test_a_stamp_from_the_current_second_could_miss_a_change(): void
    {
        self::assertTrue(ConfigSourceStamps::couldMissAChange(['/a' => '999:10:1', '/b' => '1000:10:2'], 1_000));
    }

    public function test_a_missing_path_cannot_miss_a_change(): void
    {
        self::assertFalse(ConfigSourceStamps::couldMissAChange(['/missing' => ''], 1_000));
    }

    public function test_packing_round_trips(): void
    {
        $stamps = ['/app/config/a.php' => '999:10:1', '/app/config' => '998:96:2', '/app/override' => ''];

        self::assertSame($stamps, ConfigSourceStamps::unpack(ConfigSourceStamps::pack($stamps)));
    }

    private function write(string $name, string $content, int $mtime): string
    {
        $file = $this->dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($file, $content);
        touch($file, $mtime);
        $this->created[] = $file;

        return $file;
    }
}
