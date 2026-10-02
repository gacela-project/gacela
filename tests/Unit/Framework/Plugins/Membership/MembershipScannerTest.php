<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins\Membership;

use Countable;
use Gacela\Framework\Plugins\Membership\ListenerMember;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use Gacela\Framework\Plugins\Membership\MembershipScanner;
use Gacela\Framework\Plugins\Membership\PluginMember;
use LogicException;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_reverse;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class MembershipScannerTest extends TestCase
{
    private string $root;

    private string $namespace;

    /** @var list<string> */
    private array $files = [];

    /** @var list<string> */
    private array $dirs = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-scan-' . uniqid('', true);
        $this->namespace = 'GacelaScanFixture' . uniqid();
        $this->makeDir($this->root);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        foreach (array_reverse($this->dirs) as $dir) {
            @rmdir($dir);
        }
    }

    /**
     * Valid PHP, and a shape a formatter can leave behind.
     */
    public function test_an_attribute_on_the_same_line_as_the_class_is_found(): void
    {
        $this->declare('SameLine.php', 'SameLine', sameLine: true);

        self::assertSame(['SameLine'], $this->foundIn([$this->root]));
    }

    public function test_a_plugin_without_a_priority_has_priority_zero(): void
    {
        $this->declare('Plain.php', 'Plain');

        $members = MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace])->members()->plugins;

        self::assertSame(0, $members[0]->priority);
        self::assertSame(Countable::class, $members[0]->contract);
    }

    public function test_a_class_outside_the_project_namespaces_is_not_read(): void
    {
        $this->declare('Elsewhere.php', 'Elsewhere');

        self::assertSame([], MembershipScanner::forPaths([$this->root], $this->root, ['App'])->members()->plugins);
    }

    /**
     * A namespace is matched up to a separator, whether or not it was declared
     * with a trailing one: `App` covers `App\Billing`, not `AppStuff`.
     */
    public function test_a_project_namespace_matches_whole_segments(): void
    {
        $this->declare('Inside.php', 'Inside');

        self::assertSame(['Inside'], $this->foundIn([$this->root], [$this->namespace . '\\']));
        self::assertSame([], $this->foundIn([$this->root], [$this->namespace . 'X']));
        self::assertSame([], $this->foundIn([$this->root], [substr($this->namespace, 0, -1)]));
    }

    public function test_a_module_path_is_resolved_against_the_root(): void
    {
        $this->declare('a' . DIRECTORY_SEPARATOR . 'InA.php', 'InA');

        self::assertSame(['InA'], $this->foundIn(['a'], root: $this->root . DIRECTORY_SEPARATOR));
    }

    public function test_an_absolute_module_path_is_taken_as_it_is(): void
    {
        $this->declare('abs' . DIRECTORY_SEPARATOR . 'InAbs.php', 'InAbs');

        self::assertSame(['InAbs'], $this->foundIn([$this->root . DIRECTORY_SEPARATOR . 'abs'], root: '/nowhere'));
    }

    public function test_no_module_paths_means_the_whole_root(): void
    {
        $this->declare('deep' . DIRECTORY_SEPARATOR . 'InDeep.php', 'InDeep');

        self::assertSame(['InDeep'], $this->foundIn([]));
    }

    public function test_a_module_path_that_is_not_a_directory_is_skipped_not_fatal(): void
    {
        $this->declare('real' . DIRECTORY_SEPARATOR . 'InReal.php', 'InReal');

        self::assertSame(['InReal'], $this->foundIn(['missing', 'real']));
    }

    /**
     * `vendor/` and dot directories are never walked, the same rule the module
     * scan follows.
     */
    public function test_vendor_and_dot_directories_are_not_walked(): void
    {
        $this->declare('vendor' . DIRECTORY_SEPARATOR . 'InVendor.php', 'InVendor');
        $this->declare('.hidden' . DIRECTORY_SEPARATOR . 'InHidden.php', 'InHidden');
        $this->declare('src' . DIRECTORY_SEPARATOR . 'InSrc.php', 'InSrc');

        self::assertSame(['InSrc'], $this->foundIn([]));
    }

    /**
     * Two entrypoints of one application that scan different module paths or
     * namespaces would otherwise read each other's members.
     */
    public function test_each_scan_has_its_own_cache_file(): void
    {
        $billing = MembershipCache::forScan('/cache', '/app', ['src/Billing'], ['App']);

        self::assertNotSame($billing->path(), MembershipCache::forScan('/cache', '/app', ['src'], ['App'])->path());
        self::assertNotSame($billing->path(), MembershipCache::forScan('/cache', '/app', ['src/Billing'], ['Other'])->path());
        self::assertSame($billing->path(), MembershipCache::forScan('/cache', '/app', ['src/Billing'], ['App'])->path());
    }

    public function test_a_listener_without_an_event_names_the_method(): void
    {
        $this->write('Untyped.php', 'final class Untyped { #[\\Gacela\\Framework\\Attribute\\AsListener] public function on(object $event): void {} }');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Untyped::on() has #[AsListener] and no event');

        MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace])->members();
    }

    /**
     * Read at the class that declares it, once, and not again for every
     * class that inherits it.
     */
    public function test_an_inherited_listener_is_read_once(): void
    {
        $this->write('Base.php', 'class Base { #[\Gacela\Framework\Attribute\AsListener(\\' . Countable::class . '::class)] public function on(object $event): void {} }');
        $this->write('Child.php', 'final class Child extends Base { #[\\Gacela\\Framework\\Attribute\\AsListener] public function onCountable(\\Countable $event): void {} }');

        $listeners = MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace])->members()->listeners;

        self::assertSame(
            [[Countable::class, $this->namespace . '\\Base', 'on'], [Countable::class, $this->namespace . '\\Child', 'onCountable']],
            array_map(static fn (ListenerMember $member): array => $member->toRow(), $listeners),
        );
    }

    /**
     * @param list<string> $paths
     * @param list<string>|null $namespaces
     *
     * @return list<string> the short names found
     */
    private function foundIn(array $paths, ?array $namespaces = null, ?string $root = null): array
    {
        $members = MembershipScanner::forPaths($paths, $root ?? $this->root, $namespaces ?? [$this->namespace])->members()->plugins;

        return array_map(fn (PluginMember $member): string => substr($member->plugin, strlen($this->namespace) + 1), $members);
    }

    /**
     * Loaded here, not autoloaded: the scanner asks `class_exists()`, which a
     * class already declared answers without an autoloader.
     */
    private function declare(string $relativePath, string $class, bool $sameLine = false): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . $relativePath;
        $this->makeDir(dirname($file));

        $attribute = '#[Plugin(\\' . Countable::class . '::class)]';
        $declaration = sprintf('final class %s implements \Countable { public function count(): int { return 0; } }', $class);
        file_put_contents($file, sprintf(
            "<?php\nnamespace %s;\nuse Gacela\\Framework\\Attribute\\Plugin;\n%s\n",
            $this->namespace,
            $sameLine ? $attribute . ' ' . $declaration : $attribute . "\n" . $declaration,
        ));
        $this->files[] = $file;

        require_once $file;
    }

    private function write(string $relativePath, string $declaration): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . $relativePath;
        file_put_contents($file, sprintf("<?php\nnamespace %s;\n%s\n", $this->namespace, $declaration));
        $this->files[] = $file;

        require_once $file;
    }

    private function makeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
            $this->dirs[] = $dir;
        }
    }
}
