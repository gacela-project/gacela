<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\StubHealthCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function bin2hex;
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

    private function publish(string $filename, string $contents): void
    {
        file_put_contents($this->stubsDir . '/' . $filename, $contents);
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
