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
use IteratorIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
        $fileInfo = $this->createMock(SplFileInfo::class);
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

        $fileInfo = $this->createMock(SplFileInfo::class);
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
        if (!is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname($directory), FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        @rmdir($directory);
        @rmdir(dirname($directory));
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
