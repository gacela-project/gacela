<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins\Membership;

use Countable;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use Gacela\Framework\Plugins\Membership\MembershipScanner;
use Gacela\Framework\Plugins\Membership\PluginMember;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class MembershipScannerTest extends TestCase
{
    private string $dir;

    private string $file = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-scan-' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if ($this->file !== '') {
            @unlink($this->file);
        }

        @rmdir($this->dir);
    }

    /**
     * Valid PHP, and a shape a formatter can leave behind.
     */
    public function test_an_attribute_on_the_same_line_as_the_class_is_found(): void
    {
        $namespace = 'GacelaScanFixture' . uniqid();
        $this->declare(<<<PHP
            <?php
            namespace {$namespace};
            use Gacela\\Framework\\Attribute\\Plugin;
            #[Plugin(\\Countable::class)] final class SameLine implements \\Countable { public function count(): int { return 0; } }
            PHP);

        $members = MembershipScanner::forPaths([$this->dir], $this->dir, [$namespace])->plugins();

        self::assertSame([$namespace . '\\SameLine'], array_map(static fn (PluginMember $member): string => $member->plugin, $members));
        self::assertSame(Countable::class, $members[0]->contract);
    }

    public function test_a_class_outside_the_project_namespaces_is_not_read(): void
    {
        $namespace = 'GacelaScanFixture' . uniqid();
        $this->declare(<<<PHP
            <?php
            namespace {$namespace};
            use Gacela\\Framework\\Attribute\\Plugin;
            #[Plugin(\\Countable::class)]
            final class Elsewhere implements \\Countable { public function count(): int { return 0; } }
            PHP);

        self::assertSame([], MembershipScanner::forPaths([$this->dir], $this->dir, ['App'])->plugins());
    }

    /**
     * Two entrypoints of one application that scan different module paths or
     * namespaces would otherwise read each other's members.
     */
    public function test_each_scan_has_its_own_cache_file(): void
    {
        $billing = MembershipCache::forScan('/cache', '/app', ['src/Billing'], ['App']);
        $everything = MembershipCache::forScan('/cache', '/app', ['src'], ['App']);
        $otherNamespace = MembershipCache::forScan('/cache', '/app', ['src/Billing'], ['Other']);

        self::assertNotSame($billing->path(), $everything->path());
        self::assertNotSame($billing->path(), $otherNamespace->path());
        self::assertSame($billing->path(), MembershipCache::forScan('/cache', '/app', ['src/Billing'], ['App'])->path());
    }

    /**
     * Loaded here, not autoloaded: the scanner asks `class_exists()`, which a
     * class already declared answers without an autoloader.
     */
    private function declare(string $source): void
    {
        $this->file = $this->dir . DIRECTORY_SEPARATOR . 'Fixture.php';
        file_put_contents($this->file, $source);
        require_once $this->file;
    }
}
