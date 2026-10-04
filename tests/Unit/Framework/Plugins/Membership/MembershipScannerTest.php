<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins\Membership;

use Countable;
use Gacela\Framework\Plugins\Membership\ListenerMember;
use Gacela\Framework\Plugins\Membership\Members;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use Gacela\Framework\Plugins\Membership\MembershipScanner;
use Gacela\Framework\Plugins\Membership\PluginMember;
use Gacela\Framework\Plugins\Membership\TagMember;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_reverse;
use function class_exists;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function str_replace;
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

    /**
     * A refused package installed from a path inside the module paths.
     */
    public function test_an_excluded_directory_is_not_read(): void
    {
        $this->declare('Kept.php', 'Kept');
        $this->declare('packages' . DIRECTORY_SEPARATOR . 'refused' . DIRECTORY_SEPARATOR . 'Refused.php', 'Refused');

        $members = MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace], [], [$this->root . '/packages/refused/'])->members();

        self::assertSame([$this->namespace . '\\Kept'], array_map(static fn (PluginMember $member): string => $member->plugin, $members->plugins));
    }

    /**
     * The refused directory is normalized, so the walked one has to be too.
     */
    public function test_an_excluded_directory_is_not_read_through_a_dot_segment_module_path(): void
    {
        $this->declare('Kept.php', 'Kept');
        $this->declare('packages' . DIRECTORY_SEPARATOR . 'refused' . DIRECTORY_SEPARATOR . 'Refused.php', 'Refused');

        $members = MembershipScanner::forPaths(['./', 'packages/../'], $this->root, [$this->namespace], [], [$this->root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'refused'])->members();

        self::assertSame([$this->namespace . '\\Kept'], array_map(static fn (PluginMember $member): string => $member->plugin, $members->plugins));
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

    /**
     * Reported, not thrown: the plugin stacks and tags of the same scan must
     * keep working.
     */
    public function test_a_listener_without_an_event_is_a_problem(): void
    {
        $this->write('Untyped.php', 'final class Untyped { #[\\Gacela\\Framework\\Attribute\\AsListener] public function on(object $event): void {} }');

        $members = MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace])->members();

        self::assertSame([], $members->listeners);
        self::assertCount(1, $members->problems);
        self::assertStringContainsString('Untyped::on() has #[AsListener] and no event', $members->problems[0]);
    }

    public function test_a_listener_on_an_abstract_class_is_a_problem(): void
    {
        $this->write('AbstractListener.php', 'abstract class AbstractListener { #[\\Gacela\\Framework\\Attribute\\AsListener] public function on(\\Countable $event): void {} }');

        $members = MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace])->members();

        self::assertSame([], $members->listeners);
        self::assertCount(1, $members->problems);
        self::assertStringContainsString('AbstractListener::on() has #[AsListener] on an abstract class', $members->problems[0]);
    }

    public function test_a_scan_with_problems_is_not_cached(): void
    {
        $cache = MembershipCache::forScan($this->root, $this->root, [], []);

        self::assertFalse($cache->write(new Members(problems: ['broken'])));
        self::assertFileDoesNotExist($cache->path());
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
     * The class names no attribute itself, so only the trait leads to it.
     */
    public function test_a_listener_a_class_takes_from_a_trait_is_found(): void
    {
        $this->write('ListensTrait.php', 'trait ListensTrait { #[\Gacela\Framework\Attribute\AsListener] public function onCountable(\Countable $event): void {} }');
        $this->write('UsesTrait.php', 'final class UsesTrait { use ListensTrait; }');
        $this->write('Unrelated.php', 'final class Unrelated {}');

        $listeners = MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace])->members()->listeners;

        self::assertSame(
            [[Countable::class, $this->namespace . '\UsesTrait', 'onCountable']],
            array_map(static fn (ListenerMember $member): array => $member->toRow(), $listeners),
        );
    }

    public function test_a_listener_a_class_takes_through_its_parent_trait_is_read_at_the_parent(): void
    {
        $this->write('ParentListensTrait.php', 'trait ParentListensTrait { #[\Gacela\Framework\Attribute\AsListener] public function onCountable(\Countable $event): void {} }');
        $this->write('ParentUsesTrait.php', 'class ParentUsesTrait { use ParentListensTrait; }');
        $this->write('ChildOfTraitUser.php', 'final class ChildOfTraitUser extends ParentUsesTrait {}');

        $listeners = MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace])->members()->listeners;

        self::assertSame(
            [[Countable::class, $this->namespace . '\ParentUsesTrait', 'onCountable']],
            array_map(static fn (ListenerMember $member): array => $member->toRow(), $listeners),
        );
    }

    /**
     * @param list<string> $declarations
     */
    #[DataProvider('validDeclarationsARegexMisread')]
    public function test_a_tag_is_found_however_the_file_is_written(string $source, array $declarations): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . 'Source.php';
        file_put_contents($file, str_replace('NS', $this->namespace, $source));
        $this->files[] = $file;
        require_once $file;

        self::assertSame($declarations, $this->taggedIn());
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function validDeclarationsARegexMisread(): iterable
    {
        yield 'a grouped import' => [
            "<?php\nnamespace NS;\nuse Gacela\\Framework\\{Attribute\\Tag};\n#[Tag('t')]\nfinal class Grouped {}\n",
            ['Grouped'],
        ];
        yield 'an aliased namespace import' => [
            "<?php\nnamespace NS;\nuse Gacela\\Framework as G;\n#[G\\Attribute\\Tag('t')]\nfinal class Aliased {}\n",
            ['Aliased'],
        ];
        yield 'a braced namespace' => [
            "<?php\nnamespace NS {\n    use Gacela\\Framework\\Attribute\\Tag;\n    #[Tag('t')]\n    final class Braced {}\n}\n",
            ['Braced'],
        ];
        yield 'keywords in another case' => [
            "<?php\nNamespace NS;\nuse Gacela\\Framework\\Attribute\\Tag;\n#[Tag('t')]\nFinal Class Upper {}\n",
            ['Upper'],
        ];
        yield 'a comment line starting with class' => [
            "<?php\nnamespace NS;\nuse Gacela\\Framework\\Attribute\\Tag;\n/*\nclass names in this comment are not declarations\n*/\n#[Tag('t')]\nfinal class Commented {}\n",
            ['Commented'],
        ];
        yield 'two classes in one file' => [
            "<?php\nnamespace NS;\nuse Gacela\\Framework\\Attribute\\Tag;\n#[Tag('t')]\nfinal class First {}\n#[Tag('t')]\nfinal class Second {}\n",
            ['First', 'Second'],
        ];
    }

    public function test_a_file_that_only_imports_other_gacela_classes_is_not_loaded(): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . 'NotLoaded.php';
        file_put_contents($file, sprintf("<?php\nnamespace %s;\nuse Gacela\\Framework\\AbstractFacade;\n#[\\Attribute]\nfinal class NotLoaded extends AbstractFacade {}\n", $this->namespace));
        $this->files[] = $file;

        MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace])->members();

        self::assertFalse(class_exists($this->namespace . '\\NotLoaded', false));
    }

    /**
     * @return list<string> the short names tagged, sorted
     */
    private function taggedIn(): array
    {
        $tags = MembershipScanner::forPaths([$this->root], $this->root, [$this->namespace])->members()->tags;

        return array_map(fn (TagMember $member): string => substr($member->class, strlen($this->namespace) + 1), $tags);
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
