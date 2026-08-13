<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\BinGacela;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;

use function array_diff;
use function array_map;
use function array_values;
use function basename;
use function class_exists;
use function glob;
use function implode;
use function is_subclass_of;
use function sort;
use function sprintf;

/**
 * `ConsoleProvider::commands()` is a hand-written list, and it is the one
 * `vendor/bin/gacela` reads. Both bridges grew a guard over their own lists
 * after three commands had been missing from them; the primary path never had
 * one.
 *
 * That ordering is the trap. Add a command and forget every registry: the
 * bridge tests fail and name the bridges, so the fix looks complete once they
 * are green -- while the command the CLI ships is still unreachable from the
 * CLI. The failure that fires points somewhere other than the hole.
 *
 * Asserted through a booted application rather than off the provider array, so
 * it covers the whole chain: two commands answering the same `getName()` would
 * leave only one registered, and `ConsoleBootstrap` keys by name.
 */
final class RegisteredCommandsTest extends TestCase
{
    private const string COMMAND_DIR = __DIR__ . '/../../../../src/Console/Infrastructure/Command';

    private const string COMMAND_NAMESPACE = 'Gacela\Console\Infrastructure\Command\\';

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    public function test_bin_gacela_can_run_every_command_gacela_ships(): void
    {
        $registered = array_map(
            static fn (Command $command): string => $command::class,
            array_values((new ConsoleBootstrap())->all()),
        );

        $missing = array_values(array_diff($this->everyCommandClass(), $registered));

        self::assertSame([], $missing, sprintf(
            'These commands exist but `vendor/bin/gacela` cannot run them: %s',
            implode(', ', $missing),
        ));
    }

    /**
     * Read off the directory rather than listed again here, or this test would
     * be the same list carrying the same gap.
     *
     * @return list<class-string<Command>>
     */
    private function everyCommandClass(): array
    {
        $classes = [];

        foreach (glob(self::COMMAND_DIR . '/*.php') ?: [] as $file) {
            /** @var class-string $class */
            $class = self::COMMAND_NAMESPACE . basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, Command::class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            /** @var class-string<Command> $class */
            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }
}
