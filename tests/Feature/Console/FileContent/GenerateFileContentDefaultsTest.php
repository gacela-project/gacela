<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\FileContent;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\CommandArguments\CommandArguments;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function is_dir;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * `$withShortName` defaults to false, which is what makes a generated pillar
 * carry its module's name: `CheckoutFacade`, not `Facade`. Every existing test
 * passes the flag explicitly, so the default itself was never pinned — and a
 * default nobody asserts is a default nobody notices changing.
 */
final class GenerateFileContentDefaultsTest extends TestCase
{
    private string $moduleDir = '';

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->moduleDir = sys_get_temp_dir() . '/gacela-shortname-' . bin2hex(random_bytes(4)) . '/Checkout';

        // FileContentIo::mkdir() is not recursive, so it can create one level
        // at a time. `make:file` targets a module that already exists, which is
        // what this mirrors: the module directory is there, the sub-directory
        // below it is what the generator creates.
        mkdir($this->moduleDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->moduleDir));
    }

    public function test_by_default_the_module_name_prefixes_the_generated_class(): void
    {
        $path = (new ConsoleFacade())->generateFileContent($this->commandArguments(), 'Facade');

        self::assertStringEndsWith('/CheckoutFacade.php', $path);
    }

    public function test_the_short_name_drops_the_module_prefix(): void
    {
        $path = (new ConsoleFacade())->generateFileContent($this->commandArguments(), 'Facade', true);

        self::assertStringEndsWith('/Facade.php', $path);
        self::assertStringNotContainsString('CheckoutFacade', $path);
    }

    public function test_the_service_generator_defaults_to_the_module_name_too(): void
    {
        $path = (new ConsoleFacade())->generateServiceFileContent($this->commandArguments(), 'Facade');

        self::assertStringEndsWith('/CheckoutFacade.php', $path);
    }

    public function test_the_service_generator_short_name_drops_the_module_prefix(): void
    {
        $path = (new ConsoleFacade())->generateServiceFileContent($this->commandArguments(), 'Facade', true);

        self::assertStringEndsWith('/Facade.php', $path);
        self::assertStringNotContainsString('CheckoutFacade', $path);
    }

    public function test_the_service_generator_places_the_file_in_a_sub_directory(): void
    {
        $path = (new ConsoleFacade())->generateServiceFileContent(
            $this->commandArguments(),
            'Facade',
            true,
            'Application',
        );

        self::assertStringEndsWith('/Checkout/Application/Facade.php', $path);
    }

    private function commandArguments(): CommandArguments
    {
        return new CommandArguments('App\\Checkout', $this->moduleDir);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDirectory($entry) : unlink($entry);
        }

        rmdir($directory);
    }
}
