<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ProvidedShape;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\ConsoleProvider;
use Gacela\Console\Domain\CommandArguments\CommandArguments;
use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;

use function bin2hex;
use function dirname;
use function is_dir;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * `getProvidedDependency()` returns `mixed`, so everything reading a provided
 * value narrows it defensively before use. `extendService()` is a public API and
 * the realistic way a plugin appends to those lists, which makes "a plugin put
 * the wrong thing in" a reachable state rather than a hypothetical one.
 *
 * These assert the narrowing actually narrows. Without it the console either
 * fatals on a non-Command or writes a file from a non-string template.
 */
final class MalformedProvidedValuesTest extends TestCase
{
    private string $moduleDir = '';

    protected function setUp(): void
    {
        $this->moduleDir = sys_get_temp_dir() . '/gacela-provided-' . bin2hex(random_bytes(4)) . '/Checkout';
        mkdir($this->moduleDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->moduleDir));
    }

    public function test_a_non_command_added_to_the_command_list_is_dropped(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->extendService(
                ConsoleProvider::COMMANDS,
                static fn (array $commands): array => [...$commands, 'not-a-command', 42],
            );
        });

        // ConsoleBootstrap is what consumes the list: a non-Command reaching it
        // fatals on ->getName(), so the filter is what keeps `bin/gacela`
        // starting at all.
        $commands = (new ConsoleBootstrap())->all();

        self::assertNotEmpty($commands);
        self::assertContainsOnlyInstancesOf(Command::class, $commands);
    }

    /**
     * A provider that returns something that is not a list at all must degrade
     * to "no commands", not fatal: `foreach` over a scalar is a TypeError, and
     * `bin/gacela` would stop starting entirely.
     */
    public function test_a_command_list_that_is_not_an_array_does_not_break_the_console(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->extendService(
                ConsoleProvider::COMMANDS,
                static fn (mixed $commands): mixed => 'not-a-list',
            );
        });

        $commands = (new ConsoleBootstrap())->all();

        foreach ($commands as $command) {
            self::assertInstanceOf(Command::class, $command);
        }
    }

    /**
     * The same shape one method over: a template map that is not a map at all
     * must degrade to "no templates", so `make:file` reports an unknown template
     * instead of the whole console fatalling inside a foreach.
     */
    public function test_a_template_map_that_is_not_an_array_does_not_break_the_generator(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->extendService(
                ConsoleProvider::TEMPLATE_BY_FILENAME_MAP,
                static fn (mixed $map): mixed => 'not-a-map',
            );
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unknown template for 'Facade'?");

        (new ConsoleFacade())->generateFileContent(
            new CommandArguments('App\\Checkout', $this->moduleDir),
            'Facade',
        );
    }

    public function test_a_non_string_template_entry_is_dropped(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->extendService(
                ConsoleProvider::TEMPLATE_BY_FILENAME_MAP,
                static fn (array $map): array => [...$map, 'Broken' => 123],
            );
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unknown template for 'Broken'?");

        (new ConsoleFacade())->generateFileContent(
            new CommandArguments('App\\Checkout', $this->moduleDir),
            'Broken',
        );
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
