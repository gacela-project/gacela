<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Infrastructure\ModuleVersion;

use Gacela\Console\Infrastructure\ModuleVersion\ArrayModuleVersionParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ArrayModuleVersionParserTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = (string)tempnam(sys_get_temp_dir(), 'gacela_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function test_is_available(): void
    {
        $parser = new ArrayModuleVersionParser();

        self::assertTrue($parser->isAvailable());
    }

    public function test_parse_valid_version_file(): void
    {
        file_put_contents($this->tempFile, <<<'PHP'
<?php

return [
    'User' => [
        'version' => '1.2.0',
        'requires' => [
            'Auth' => '^1.0',
        ],
    ],
    'Auth' => [
        'version' => '1.5.0',
    ],
];
PHP);

        $parser = new ArrayModuleVersionParser();
        $modules = $parser->parseVersionsFile($this->tempFile);

        self::assertCount(2, $modules);
        self::assertArrayHasKey('User', $modules);
        self::assertArrayHasKey('Auth', $modules);

        $userModule = $modules['User'];
        self::assertSame('User', $userModule->moduleName);
        self::assertSame('1.2.0', $userModule->version);
        self::assertArrayHasKey('Auth', $userModule->requiredModules);
        self::assertSame('^1.0', $userModule->requiredModules['Auth']);

        $authModule = $modules['Auth'];
        self::assertSame('Auth', $authModule->moduleName);
        self::assertSame('1.5.0', $authModule->version);
        self::assertEmpty($authModule->requiredModules);
    }

    public function test_parse_file_not_found(): void
    {
        $parser = new ArrayModuleVersionParser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Version file not found');

        $parser->parseVersionsFile('/nonexistent/file.php');
    }

    public function test_parse_invalid_file_content(): void
    {
        file_put_contents($this->tempFile, '<?php return "not an array";');

        $parser = new ArrayModuleVersionParser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Version file must return an array');

        $parser->parseVersionsFile($this->tempFile);
    }

    public function test_parse_empty_version_file(): void
    {
        file_put_contents($this->tempFile, '<?php return [];');

        $parser = new ArrayModuleVersionParser();
        $modules = $parser->parseVersionsFile($this->tempFile);

        self::assertEmpty($modules);
    }

    public function test_parse_module_with_default_version(): void
    {
        file_put_contents($this->tempFile, <<<'PHP'
<?php

return [
    'User' => [
        'requires' => [
            'Auth' => '^1.0',
        ],
    ],
];
PHP);

        $parser = new ArrayModuleVersionParser();
        $modules = $parser->parseVersionsFile($this->tempFile);

        self::assertCount(1, $modules);
        self::assertSame('0.0.0', $modules['User']->version);
    }

    public function test_parse_module_with_complex_dependencies(): void
    {
        file_put_contents($this->tempFile, <<<'PHP'
<?php

return [
    'Product' => [
        'version' => '2.0.0',
        'requires' => [
            'User' => '^1.0',
            'Catalog' => '~2.0',
            'Logger' => '>=1.5',
        ],
    ],
];
PHP);

        $parser = new ArrayModuleVersionParser();
        $modules = $parser->parseVersionsFile($this->tempFile);

        self::assertCount(1, $modules);
        $productModule = $modules['Product'];

        self::assertCount(3, $productModule->requiredModules);
        self::assertSame('^1.0', $productModule->requiredModules['User']);
        self::assertSame('~2.0', $productModule->requiredModules['Catalog']);
        self::assertSame('>=1.5', $productModule->requiredModules['Logger']);
    }
}
