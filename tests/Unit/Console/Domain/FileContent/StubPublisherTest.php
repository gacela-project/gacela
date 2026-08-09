<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\FileContent;

use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use Gacela\Console\Domain\FileContent\StubPublisher;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class StubPublisherTest extends TestCase
{
    private string $stubsDir = '';

    /** @var array<string, string> */
    private array $written = [];

    /** @var list<string> */
    private array $directories = [];

    /** @var list<string> */
    private array $onDisk = [];

    protected function setUp(): void
    {
        $this->stubsDir = sys_get_temp_dir() . '/gacela-publisher-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach ($this->onDisk as $path) {
            if (is_file($path)) {
                unlink($path);
                @rmdir(dirname($path));
            }
        }

        foreach ($this->directories as $directory) {
            @rmdir($directory);
        }

        @rmdir($this->stubsDir);
    }

    public function test_it_writes_every_built_in_stub(): void
    {
        $result = $this->publisher()->publish($this->stubsDir);

        self::assertSame([
            $this->stubsDir . '/facade-maker.txt' => 'facade',
            $this->stubsDir . '/service/facade-maker.txt' => 'service facade',
        ], $this->written);
        self::assertCount(2, $result->written);
        self::assertSame([], $result->skipped);
        self::assertFalse($result->hasSkipped());
    }

    public function test_it_creates_the_directory_each_stub_needs(): void
    {
        $this->publisher()->publish($this->stubsDir);

        self::assertContains($this->stubsDir, $this->directories);
        self::assertContains($this->stubsDir . '/service', $this->directories);
    }

    public function test_it_writes_only_the_stubs_asked_for(): void
    {
        $result = $this->publisher()->publish($this->stubsDir, ['facade-maker.txt']);

        self::assertSame([$this->stubsDir . '/facade-maker.txt' => 'facade'], $this->written);
        self::assertCount(1, $result->written);
    }

    /**
     * A published stub is a file somebody changed on purpose.
     */
    public function test_it_leaves_an_already_published_stub_alone(): void
    {
        $this->publishOnDisk('facade-maker.txt');

        $result = $this->publisher()->publish($this->stubsDir);

        self::assertSame([$this->stubsDir . '/facade-maker.txt'], $result->skipped);
        self::assertTrue($result->hasSkipped());
        self::assertArrayNotHasKey($this->stubsDir . '/facade-maker.txt', $this->written);
        self::assertArrayHasKey($this->stubsDir . '/service/facade-maker.txt', $this->written);
    }

    public function test_force_writes_over_it(): void
    {
        $this->publishOnDisk('facade-maker.txt');

        $result = $this->publisher()->publish($this->stubsDir, [], true);

        self::assertSame([], $result->skipped);
        self::assertArrayHasKey($this->stubsDir . '/facade-maker.txt', $this->written);
    }

    private function publisher(): StubPublisher
    {
        return new StubPublisher($this->io(), [
            'facade-maker.txt' => 'facade',
            'service/facade-maker.txt' => 'service facade',
        ]);
    }

    private function io(): FileContentIoInterface
    {
        return new class($this->written, $this->directories) implements FileContentIoInterface {
            /**
             * @param array<string, string> $written
             * @param list<string> $directories
             */
            public function __construct(
                private array &$written,
                private array &$directories,
            ) {
            }

            public function mkdir(string $directory): void
            {
                $this->directories[] = $directory;
            }

            public function filePutContents(string $path, string $fileContent): void
            {
                $this->written[$path] = $fileContent;
            }
        };
    }

    /**
     * Really on disk: whether a stub is already published is a question about
     * the filesystem, not about what this test recorded.
     */
    private function publishOnDisk(string $relativePath): void
    {
        $path = $this->stubsDir . '/' . $relativePath;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, 'house style');
        // Deliberately not recorded as written: the point of the test is what
        // the publisher wrote, and a helper that says it wrote this file makes
        // the two indistinguishable.
        $this->onDisk[] = $path;
    }
}
