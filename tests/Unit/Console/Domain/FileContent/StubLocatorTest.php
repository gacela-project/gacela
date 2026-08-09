<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\FileContent;

use Gacela\Console\Domain\FileContent\StubFiles;
use Gacela\Console\Domain\FileContent\StubLocator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class StubLocatorTest extends TestCase
{
    private string $stubsDir = '';

    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        $this->stubsDir = sys_get_temp_dir() . '/gacela-stubs-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $file) {
            if (is_file($file)) {
                unlink($file);
                @rmdir(dirname($file));
            }
        }

        $this->written = [];

        if (is_dir($this->stubsDir)) {
            @rmdir($this->stubsDir);
        }
    }

    public function test_the_built_in_template_is_used_when_nothing_is_published(): void
    {
        self::assertSame('built-in facade', $this->locator()->templateFor('Facade'));
    }

    public function test_a_published_stub_replaces_the_built_in_one(): void
    {
        $this->publish('facade-maker.txt', 'house style facade');

        self::assertSame('house style facade', $this->locator()->templateFor('Facade'));
    }

    /**
     * Publishing one file must not freeze the rest at the version it was copied
     * from.
     */
    public function test_the_other_files_keep_falling_back_to_the_built_in_ones(): void
    {
        $this->publish('facade-maker.txt', 'house style facade');

        $locator = $this->locator();

        self::assertSame('house style facade', $locator->templateFor('Facade'));
        self::assertSame('built-in factory', $locator->templateFor('Factory'));
    }

    public function test_a_stub_in_a_subdirectory_is_found(): void
    {
        $this->publish('service/facade-maker.txt', 'house style service facade');

        $locator = new StubLocator(
            $this->stubsDir,
            ['Facade' => 'built-in service facade'],
            StubFiles::service(),
        );

        self::assertSame('house style service facade', $locator->templateFor('Facade'));
    }

    /**
     * The published Facade of the basic set is not the published Facade of the
     * service set: they generate different files and live under different names.
     */
    public function test_the_two_template_sets_do_not_share_a_published_stub(): void
    {
        $this->publish('facade-maker.txt', 'basic');

        $service = new StubLocator(
            $this->stubsDir,
            ['Facade' => 'built-in service facade'],
            StubFiles::service(),
        );

        self::assertSame('built-in service facade', $service->templateFor('Facade'));
    }

    public function test_without_a_stubs_directory_nothing_is_published(): void
    {
        $locator = new StubLocator('', ['Facade' => 'built-in facade'], StubFiles::basic());

        self::assertSame('built-in facade', $locator->templateFor('Facade'));
    }

    public function test_an_unknown_filename_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nope');

        $this->locator()->templateFor('Nope');
    }

    private function locator(): StubLocator
    {
        return new StubLocator(
            $this->stubsDir,
            ['Facade' => 'built-in facade', 'Factory' => 'built-in factory'],
            StubFiles::basic(),
        );
    }

    private function publish(string $relativePath, string $contents): void
    {
        $path = $this->stubsDir . '/' . $relativePath;
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);
        $this->written[] = $path;
    }
}
