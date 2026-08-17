<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\SymfonyBridge\GacelaCommands;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;

use function array_diff;
use function array_keys;
use function array_values;
use function basename;
use function class_exists;
use function glob;
use function is_subclass_of;
use function sort;
use function sprintf;

/**
 * The registry is a hand-written list, so a command added to the framework is a
 * command Symfony projects do not get until someone remembers this file.
 *
 * Three had been missed -- `dto:generate`, `ide:meta` and `stubs:publish` --
 * which is three commands `bin/console` could not run while `vendor/bin/gacela`
 * could. All three write files into the project exactly like `make:module` and
 * `init`, which were listed, so nothing distinguished them but the order they
 * were written in.
 */
final class GacelaCommandsTest extends TestCase
{
    private const string COMMAND_DIR = __DIR__ . '/../../../src/Console/Infrastructure/Command';

    private const string COMMAND_NAMESPACE = 'Gacela\Console\Infrastructure\Command\\';

    public function test_every_gacela_command_is_registered(): void
    {
        $registered = array_keys(GacelaCommands::names());
        $missing = array_values(array_diff($this->everyCommandClass(), $registered));

        self::assertSame([], $missing, sprintf(
            'These commands exist but no Symfony project can run them: %s',
            implode(', ', $missing),
        ));
    }

    public function test_nothing_registered_has_stopped_being_a_command(): void
    {
        $extra = array_values(array_diff(array_keys(GacelaCommands::names()), $this->everyCommandClass()));

        self::assertSame([], $extra, 'the list points at something that is no longer a command');
    }

    /**
     * Read off the directory rather than listed again here, or this test would
     * be the same list with the same gap.
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
