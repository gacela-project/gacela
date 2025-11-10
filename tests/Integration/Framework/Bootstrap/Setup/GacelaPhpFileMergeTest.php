<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class GacelaPhpFileMergeTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/gacela_merge_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        $reflection = new ReflectionClass(Gacela::class);
        $method = $reflection->getMethod('resetCache');
        $method->invoke(null);
    }

    public function test_factories_from_gacela_php_file_survive_merge(): void
    {
        $gacelaPhpContent = <<<'PHP'
<?php
use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    $config->addFactory('test-factory', static fn (): \stdClass => new \stdClass());
};
PHP;

        file_put_contents($this->tempDir . '/gacela.php', $gacelaPhpContent);

        Gacela::bootstrap($this->tempDir);

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $instance1 = $container->get('test-factory');
        $instance2 = $container->get('test-factory');

        self::assertInstanceOf(stdClass::class, $instance1);
        self::assertInstanceOf(stdClass::class, $instance2);
        self::assertNotSame($instance1, $instance2, 'Factory should return new instances');
    }

    public function test_protected_services_from_gacela_php_file_survive_merge(): void
    {
        $callable = static fn (): string => 'protected-value';

        Gacela::bootstrap($this->tempDir, static function (GacelaConfig $config) use ($callable): void {
            $config->addProtected('test-protected', $callable);
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $result = $container->get('test-protected');

        self::assertSame($callable, $result, 'Protected service should return the closure itself');
    }

    public function test_aliases_from_gacela_php_file_survive_merge(): void
    {
        Gacela::bootstrap($this->tempDir, static function (GacelaConfig $config): void {
            $config->addFactory('original-service', static fn (): stdClass => new stdClass());
            $config->addAlias('my-alias', 'original-service');
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $instance = $container->get('my-alias');

        self::assertInstanceOf(stdClass::class, $instance, 'Alias should resolve to factory service');
    }

    public function test_all_container_features_from_gacela_php_file_survive_merge(): void
    {
        $protectedCallable = static fn (): string => 'protected';

        Gacela::bootstrap($this->tempDir, static function (GacelaConfig $config) use ($protectedCallable): void {
            $config->addFactory('my-factory', static fn (): stdClass => new stdClass());
            $config->addProtected('my-protected', $protectedCallable);
            $config->addAlias('my-alias', 'my-factory');
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        // Test factory
        $factory1 = $container->get('my-factory');
        $factory2 = $container->get('my-factory');
        self::assertNotSame($factory1, $factory2);

        // Test protected
        $protected = $container->get('my-protected');
        self::assertSame($protectedCallable, $protected);

        // Test alias
        $aliased = $container->get('my-alias');
        self::assertInstanceOf(stdClass::class, $aliased);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
