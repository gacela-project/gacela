<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\AllAppModules;

use ArrayIterator;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeFile;
use Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeFinder;
use Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeProblem;
use IteratorIterator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SplFileInfo;

use function basename;
use function bin2hex;
use function dirname;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * Every other check works from the modules discovery found, so a module that
 * was never found is invisible to all of them. `list:modules` names the cause
 * only when *nothing* was discovered -- one broken Facade in fifty leaves
 * forty-nine modules and silence.
 */
final class UndiscoveredFacadeFinderTest extends TestCase
{
    private string $tempDir = '';

    /** @var list<string> */
    private array $createdFiles = [];

    /** @var list<string> */
    private array $createdDirs = [];

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-undiscovered-' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0777, true);
        $this->createdDirs[] = $this->tempDir;
    }

    protected function tearDown(): void
    {
        // Names exactly what this test created, and refuses to touch anything
        // outside the directory setUp() made.
        $prefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-undiscovered-';

        foreach ($this->createdFiles as $file) {
            self::assertStringStartsWith($prefix, $file);
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach (array_reverse($this->createdDirs) as $dir) {
            self::assertStringStartsWith($prefix, $dir);
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }

        $this->createdFiles = [];
        $this->createdDirs = [];
    }

    /**
     * The fault that costs an investigation: the file is there, the class is
     * named right, and composer cannot map the namespace -- so discovery skips
     * it and nothing anywhere says why.
     */
    public function test_reports_a_facade_php_cannot_load(): void
    {
        $path = $this->writeModuleFile('Blog', 'BlogFacade', 'Nowhere\\Nothing\\Blog');

        $found = $this->find($path);

        self::assertCount(1, $found);
        self::assertSame(UndiscoveredFacadeProblem::NotLoadable, $found[0]->problem);
        self::assertSame('Nowhere\\Nothing\\Blog\\BlogFacade', $found[0]->className);
        self::assertSame($path, $found[0]->path);
    }

    /**
     * The beginner's version of the same silence: the class loads fine and
     * simply is not a Facade, because the `extends` was never written.
     *
     * The fixture is a plain class on purpose -- one that really extended
     * `AbstractFacade` would be a new module in `tests/`, and module counts are
     * asserted elsewhere.
     */
    public function test_reports_a_facade_that_does_not_extend_abstract_facade(): void
    {
        $path = dirname(__DIR__, 5) . '/tests/Unit/Console/Domain/AllAppModules/Fixtures/Shop/ShopFacade.php';

        $found = $this->find($path);

        self::assertCount(1, $found);
        self::assertSame(UndiscoveredFacadeProblem::NotAFacade, $found[0]->problem);
        self::assertStringEndsWith('Shop\\ShopFacade', $found[0]->className);
    }

    /**
     * A real module is found, so it is not a finding. Without this the two above
     * would pass for a finder that reports every file it is handed.
     */
    public function test_a_real_facade_is_not_reported(): void
    {
        $file = (new ReflectionClass(ConsoleFacade::class))->getFileName();
        self::assertIsString($file);

        self::assertSame([], $this->find($file));
    }

    /**
     * `Facade` is an ordinary word. This finder's own `UndiscoveredFacadeFile` ends
     * in it, and so will a project's `NullFacade` -- reporting those would bury
     * the case worth reading under classes that were never modules. Caught on
     * the first run of this check against gacela's own source.
     */
    public function test_a_class_merely_ending_in_facade_is_not_reported(): void
    {
        $path = $this->writeModuleFile('Blog', 'NullFacade', 'Nowhere\\Nothing\\Blog');

        self::assertSame([], $this->find($path));
    }

    /**
     * A `--short-name` module names the class exactly `Facade`, so the
     * directory-name rule has to admit it.
     */
    public function test_a_short_name_facade_is_still_recognised(): void
    {
        $path = $this->writeModuleFile('Blog', 'Facade', 'Nowhere\\Nothing\\Blog');

        $found = $this->find($path);

        self::assertCount(1, $found);
        self::assertSame(UndiscoveredFacadeProblem::NotLoadable, $found[0]->problem);
    }

    /**
     * The suffix set is configurable, so a project on `addSuffixTypeFacade()`
     * gets the same answer for the name it actually uses.
     */
    public function test_a_configured_suffix_is_recognised_too(): void
    {
        $path = $this->writeModuleFile('Blog', 'BlogPublicApi', 'Nowhere\\Nothing\\Blog');

        $found = $this->find($path, ['Facade', 'PublicApi']);

        self::assertCount(1, $found);
        self::assertSame('Nowhere\\Nothing\\Blog\\BlogPublicApi', $found[0]->className);
    }

    public function test_a_file_that_is_not_php_is_ignored(): void
    {
        $path = $this->writeModuleFile('Blog', 'BlogFacade', 'Nowhere\\Nothing\\Blog');

        self::assertSame([], $this->find($path, ['Facade'], extension: 'txt'));
    }

    /**
     * @param list<string> $suffixes
     *
     * @return list<UndiscoveredFacadeFile>
     */
    private function find(string $path, array $suffixes = ['Facade'], string $extension = 'php'): array
    {
        $fileInfo = $this->createStub(SplFileInfo::class);
        $fileInfo->method('isFile')->willReturn(true);
        $fileInfo->method('getExtension')->willReturn($extension);
        $fileInfo->method('getRealPath')->willReturn($path);
        $fileInfo->method('getFilename')->willReturn(basename($path));

        return (new UndiscoveredFacadeFinder(
            new IteratorIterator(new ArrayIterator([$fileInfo])),
            $suffixes,
        ))->find();
    }

    private function writeModuleFile(string $moduleName, string $className, string $namespace): string
    {
        $dir = $this->tempDir . DIRECTORY_SEPARATOR . $moduleName;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
            $this->createdDirs[] = $dir;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $className . '.php';
        file_put_contents($path, sprintf("<?php\n\nnamespace %s;\n\nclass %s\n{\n}\n", $namespace, $className));
        $this->createdFiles[] = $path;

        return $path;
    }
}
