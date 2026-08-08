<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\AllAppModules;

use ArrayIterator;
use FilesystemIterator;
use Gacela\Console\Domain\AllAppModules\AllAppModulesFinder;
use Gacela\Console\Domain\AllAppModules\AppModuleCreator;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use IteratorIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use SplFileInfo;

use function dirname;

final class AllAppModulesFinderTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(dirname(__DIR__, 5));
    }

    public function test_skips_entries_marked_as_directory(): void
    {
        $fileInfo = $this->createStub(SplFileInfo::class);
        $fileInfo->method('isFile')->willReturn(false);
        $fileInfo->method('getExtension')->willReturn('php');
        $fileInfo->method('getRealPath')->willReturn($this->module1FacadePath());
        $fileInfo->method('getFilename')->willReturn('Module1Facade.php');

        $finder = new AllAppModulesFinder(
            $this->iteratorFor($fileInfo),
            $this->createAppModuleCreator(),
        );

        self::assertSame([], $finder->findAllAppModules(''));
    }

    public function test_skips_vendor_directories_only_when_segment_matches(): void
    {
        $tempDir = $this->createTempModuleDirectory('vendormodule');
        $filePath = $tempDir . '/TempFacade.php';
        $className = 'TempAllAppModulesVendor\\TempFacade';

        $this->writeTempFacadeFile($filePath, $className);
        require_once $filePath;

        $fileInfo = $this->createStub(SplFileInfo::class);
        $fileInfo->method('isFile')->willReturn(true);
        $fileInfo->method('getExtension')->willReturn('php');
        $fileInfo->method('getRealPath')->willReturn($filePath);
        $fileInfo->method('getFilename')->willReturn('TempFacade.php');

        $finder = new AllAppModulesFinder(
            $this->iteratorFor($fileInfo),
            $this->createAppModuleCreator(),
        );

        try {
            $modules = $finder->findAllAppModules('');
            self::assertCount(1, $modules);
            self::assertSame($className, $modules[0]->facadeClass());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * `RealFacade extends ProjectBaseFacade extends AbstractFacade` is a normal
     * shape, and comparing the immediate parent by name dropped every module
     * built that way from list, doctor, graph and cache-warm.
     */
    public function test_finds_a_facade_that_extends_abstract_facade_indirectly(): void
    {
        $tempDir = $this->createTempModuleDirectory('indirectmodule');
        $filePath = $tempDir . '/IndirectFacade.php';
        $className = 'TempAllAppModulesIndirect\\IndirectFacade';

        file_put_contents($filePath, <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace TempAllAppModulesIndirect;

            use Gacela\Framework\AbstractFacade;

            abstract class ProjectBaseFacade extends AbstractFacade
            {
            }

            final class IndirectFacade extends ProjectBaseFacade
            {
            }
            PHP);
        require_once $filePath;

        $fileInfo = $this->createStub(SplFileInfo::class);
        $fileInfo->method('isFile')->willReturn(true);
        $fileInfo->method('getExtension')->willReturn('php');
        $fileInfo->method('getRealPath')->willReturn($filePath);
        $fileInfo->method('getFilename')->willReturn('IndirectFacade.php');

        $finder = new AllAppModulesFinder(
            $this->iteratorFor($fileInfo),
            $this->createAppModuleCreator(),
        );

        try {
            $modules = $finder->findAllAppModules('');
            self::assertCount(1, $modules);
            self::assertSame($className, $modules[0]->facadeClass());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * Widening to "any descendant" must not widen to "any class at all".
     */
    public function test_a_class_that_does_not_extend_abstract_facade_is_not_a_module(): void
    {
        $tempDir = $this->createTempModuleDirectory('notafacade');
        $filePath = $tempDir . '/PlainFacade.php';

        file_put_contents($filePath, <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace TempAllAppModulesPlain;

            final class PlainFacade
            {
            }
            PHP);
        require_once $filePath;

        $fileInfo = $this->createStub(SplFileInfo::class);
        $fileInfo->method('isFile')->willReturn(true);
        $fileInfo->method('getExtension')->willReturn('php');
        $fileInfo->method('getRealPath')->willReturn($filePath);
        $fileInfo->method('getFilename')->willReturn('PlainFacade.php');

        $finder = new AllAppModulesFinder(
            $this->iteratorFor($fileInfo),
            $this->createAppModuleCreator(),
        );

        try {
            self::assertSame([], $finder->findAllAppModules(''));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function test_skips_dotfile_php_configs(): void
    {
        $tempDir = $this->createTempModuleDirectory('dotfile');
        $filePath = $tempDir . '/.php-cs-fixer.dist.php';
        file_put_contents($filePath, "<?php\n\nreturn [];\n");

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $finder = new AllAppModulesFinder($iterator, $this->createAppModuleCreator());

        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new RuntimeException('PHP error: ' . $errstr);
        });

        try {
            self::assertSame([], $finder->findAllAppModules(''));
        } finally {
            restore_error_handler();
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * A PHP file with no `namespace` declaration still yields a class name, so
     * both halves of the guard have to be able to reject on their own.
     */
    public function test_skips_a_php_file_that_declares_no_namespace(): void
    {
        $tempDir = $this->createTempModuleDirectory('nonamespace');
        $filePath = $tempDir . '/NamespacelessFacade.php';
        file_put_contents($filePath, "<?php\n\nreturn [];\n");

        $fileInfo = $this->createStub(SplFileInfo::class);
        $fileInfo->method('isFile')->willReturn(true);
        $fileInfo->method('getExtension')->willReturn('php');
        $fileInfo->method('getRealPath')->willReturn($filePath);
        $fileInfo->method('getFilename')->willReturn('NamespacelessFacade.php');

        $finder = new AllAppModulesFinder($this->iteratorFor($fileInfo), $this->createAppModuleCreator());

        try {
            self::assertSame([], $finder->findAllAppModules(''));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * A facade in the global namespace passes the class-name half of the guard
     * *and* class_exists(), so only the namespace half can reject it. Gacela
     * resolves pillars by namespace; a module without one has nothing to scan.
     */
    public function test_skips_a_facade_declared_in_the_global_namespace(): void
    {
        $tempDir = $this->createTempModuleDirectory('rootnamespace');
        $filePath = $tempDir . '/TempRootFacade.php';
        file_put_contents($filePath, <<<'PHP'
            <?php

            declare(strict_types=1);

            final class TempRootFacade extends Gacela\Framework\AbstractFacade
            {
            }
            PHP);
        require_once $filePath;

        $finder = new AllAppModulesFinder(
            $this->iteratorFor($this->fileInfoFor($filePath, 'TempRootFacade.php')),
            $this->createAppModuleCreator(),
        );

        try {
            self::assertSame([], $finder->findAllAppModules(''));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * The filter is written the way a path is (`Foo/Bar`) but matched against a
     * namespace, so the separators have to be translated first.
     */
    public function test_a_filter_written_with_slashes_matches_the_namespace(): void
    {
        $tempDir = $this->createTempModuleDirectory('slashfilter');
        $filePath = $tempDir . '/SlashFilterFacade.php';
        $className = 'TempSlashFilter\\Inner\\SlashFilterFacade';

        $this->writeTempFacadeFile($filePath, $className);
        require_once $filePath;

        $finder = new AllAppModulesFinder(
            $this->iteratorFor($this->fileInfoFor($filePath, 'SlashFilterFacade.php')),
            $this->createAppModuleCreator(),
        );

        try {
            $modules = $finder->findAllAppModules('TempSlashFilter/Inner');

            self::assertCount(1, $modules);
            self::assertSame($className, $modules[0]->facadeClass());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * A file the finder must skip only because of its extension: the class it
     * declares is loaded, so anything else about it would make it a module.
     */
    public function test_skips_a_loadable_facade_stored_in_a_non_php_file(): void
    {
        $tempDir = $this->createTempModuleDirectory('textextension');
        $filePath = $tempDir . '/TextFacade.txt';

        $this->writeTempFacadeFile($filePath, 'TempTextExtension\\TextFacade');
        require_once $filePath;

        $finder = new AllAppModulesFinder(
            $this->iteratorFor($this->fileInfoFor($filePath, 'TextFacade.txt', 'txt')),
            $this->createAppModuleCreator(),
        );

        try {
            self::assertSame([], $finder->findAllAppModules(''));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * Counterpart to the `vendormodule` case: a real `vendor/` path segment is
     * skipped even when the file behind it is a perfectly loadable facade.
     */
    public function test_skips_a_loadable_facade_inside_a_vendor_directory(): void
    {
        $tempDir = $this->createTempModuleDirectory('vendor');
        $filePath = $tempDir . '/VendorFacade.php';

        $this->writeTempFacadeFile($filePath, 'TempVendorSegment\\VendorFacade');
        require_once $filePath;

        $finder = new AllAppModulesFinder(
            // The guard looks for `vendor` . DIRECTORY_SEPARATOR in the real
            // path, so the separator has to be the platform's own one.
            $this->iteratorFor($this->fileInfoFor(
                str_replace('/', DIRECTORY_SEPARATOR, $filePath),
                'VendorFacade.php',
            )),
            $this->createAppModuleCreator(),
        );

        try {
            self::assertSame([], $finder->findAllAppModules(''));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * The file is never required, so its namespace and class name parse but the
     * class itself cannot be autoloaded -- reflecting over it would blow up.
     */
    public function test_skips_a_php_file_whose_class_cannot_be_autoloaded(): void
    {
        $tempDir = $this->createTempModuleDirectory('notautoloadable');
        $filePath = $tempDir . '/UnloadableFacade.php';

        $this->writeTempFacadeFile($filePath, 'TempNotAutoloadable\\UnloadableFacade');

        $finder = new AllAppModulesFinder(
            $this->iteratorFor($this->fileInfoFor($filePath, 'UnloadableFacade.php')),
            $this->createAppModuleCreator(),
        );

        try {
            self::assertSame([], $finder->findAllAppModules(''));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    private function fileInfoFor(string $path, string $filename, string $extension = 'php'): SplFileInfo
    {
        $fileInfo = $this->createStub(SplFileInfo::class);
        $fileInfo->method('isFile')->willReturn(true);
        $fileInfo->method('getExtension')->willReturn($extension);
        $fileInfo->method('getRealPath')->willReturn($path);
        $fileInfo->method('getFilename')->willReturn($filename);

        return $fileInfo;
    }

    private function iteratorFor(SplFileInfo ...$files): IteratorIterator
    {
        return new IteratorIterator(new ArrayIterator($files));
    }

    private function module1FacadePath(): string
    {
        return dirname(__DIR__, 4) . '/Integration/Console/AllAppModules/Domain/Module1/Module1Facade.php';
    }

    private function createTempModuleDirectory(string $directoryName): string
    {
        $tempDir = sys_get_temp_dir() . '/gacela_all_modules_' . uniqid('', true);
        $targetDir = $tempDir . '/' . $directoryName;
        mkdir($targetDir, 0777, true);

        return $targetDir;
    }

    private function writeTempFacadeFile(string $filePath, string $className): void
    {
        $namespace = substr($className, 0, strrpos($className, '\\'));
        $classBasename = substr($className, strrpos($className, '\\') + 1);
        $template = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Gacela\\Framework\\AbstractFacade;

final class {$classBasename} extends AbstractFacade
{
}
PHP;

        file_put_contents($filePath, $template);
    }

    private function removeDirectory(string $directory): void
    {
        // The temp module dir lives inside a uniquely named parent; remove the whole parent.
        DirectoryUtil::removeDir(dirname($directory));
    }

    private function createAppModuleCreator(): AppModuleCreator
    {
        return new AppModuleCreator(
            new FactoryResolver(),
            new ConfigResolver(),
            new ProviderResolver(),
        );
    }
}
