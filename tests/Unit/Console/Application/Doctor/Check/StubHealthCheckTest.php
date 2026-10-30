<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\StubHealthCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function implode;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * The stub a project publishes for a kind it declared is one the scaffolder
 * really does read, so reporting it as an edit that never takes effect is the
 * check calling `make:file` a liar.
 */
final class StubHealthCheckTest extends TestCase
{
    private const USABLE_STUB = '<?php namespace $NAMESPACE$; class $CLASS_NAME$ {}';

    private string $stubsDir = '';

    protected function setUp(): void
    {
        $this->stubsDir = sys_get_temp_dir() . '/gacela-stub-health-' . bin2hex(random_bytes(6));
        mkdir($this->stubsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->stubsDir !== '' && is_dir($this->stubsDir)) {
            $this->removeRecursively($this->stubsDir);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function publishedPaths(): iterable
    {
        foreach (['-plain', '-[v1]', '-{eu,us}', '-[v1]{eu,us}'] as $suffix) {
            foreach (['unknown.txt', 'service/unknown.txt'] as $filename) {
                yield $suffix . ':' . $filename => [$suffix, $filename];
            }
        }
    }

    #[DataProvider('publishedPaths')]
    public function test_unknown_stubs_are_found_in_literal_directories(string $suffix, string $filename): void
    {
        $this->useDirectorySuffix($suffix);
        $this->publish($filename, self::USABLE_STUB);

        self::assertArrayHasKey($filename, StubHealthCheck::readPublished($this->stubsDir));
        $result = $this->check()->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString($filename . ' matches no template', implode(' | ', $result->details));
    }

    public function test_known_and_declared_stubs_remain_usable_in_a_literal_directory(): void
    {
        $this->useDirectorySuffix('-[v1]{eu,us}');
        $this->publish('facade-maker.txt', self::USABLE_STUB);
        $this->publish('exporter-maker.txt', self::USABLE_STUB);

        self::assertSame(CheckStatus::Ok, $this->check(['Exporter'])->run()->status);
    }

    public function test_a_declared_kinds_stub_is_one_the_scaffolder_reads(): void
    {
        $this->publish('exporter-maker.txt', self::USABLE_STUB);

        $result = $this->check(['Exporter'])->run();

        self::assertSame(CheckStatus::Ok, $result->status, implode(' | ', $result->details));
    }

    public function test_the_same_file_is_reported_when_no_kind_declares_it(): void
    {
        $this->publish('exporter-maker.txt', self::USABLE_STUB);

        $result = $this->check()->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('matches no template', implode(' | ', $result->details));
    }

    public function test_a_declared_kinds_stub_still_needs_its_placeholders(): void
    {
        $this->publish('exporter-maker.txt', '<?php // nothing to substitute');

        $result = $this->check(['Exporter'])->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('$NAMESPACE$', implode(' | ', $result->details));
    }

    /**
     * @param list<string> $declaredKinds
     */
    private function check(array $declaredKinds = []): StubHealthCheck
    {
        return new StubHealthCheck(
            $this->stubsDir,
            StubHealthCheck::readPublished($this->stubsDir, $declaredKinds),
            $declaredKinds,
        );
    }

    private function useDirectorySuffix(string $suffix): void
    {
        rmdir($this->stubsDir);
        $this->stubsDir .= $suffix;
        mkdir($this->stubsDir, 0777, true);
    }

    private function publish(string $filename, string $contents): void
    {
        $path = $this->stubsDir . '/' . $filename;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    private function removeRecursively(string $directory): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($directory);
    }
}
