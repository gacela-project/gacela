<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Symfony\Component\Console\Command\Command;

use function array_map;

/**
 * Every command Gacela ships, listed once.
 *
 * There were three copies of this list: `ConsoleProvider::commands()`, which
 * `vendor/bin/gacela` reads, and a `GacelaCommands` in each bridge. Adding a
 * command meant remembering all three, and three commands had already been
 * missing from the bridges once.
 *
 * Guards were written after that -- `RegisteredCommandsTest` for the CLI and a
 * `GacelaCommandsTest` in each bridge, all reading the command directory rather
 * than restating the list -- and they work: they turn a forgotten registry into
 * a failing build. They do not make the list one list, which is what stops the
 * question being asked a fourth time.
 *
 * The guards stay. They now cover a different mistake: a command class added to
 * the directory and to no list at all.
 *
 * @internal
 */
final class CommandCatalog
{
    /** @var list<class-string<Command>> */
    private const CLASSES = [
        MakeFileCommand::class,
        MakeModuleCommand::class,
        ListModulesCommand::class,
        DebugConfigCommand::class,
        DebugContainerCommand::class,
        DebugDependenciesCommand::class,
        DebugGraphCommand::class,
        DebugModuleCommand::class,
        DebugModulesCommand::class,
        DebugProvidesCommand::class,
        CacheWarmCommand::class,
        CacheClearCommand::class,
        ValidateConfigCommand::class,
        ProfileReportCommand::class,
        DoctorCommand::class,
        InitCommand::class,
        DtoGenerateCommand::class,
        IdeMetaCommand::class,
        StubsPublishCommand::class,
    ];

    /**
     * @return list<class-string<Command>>
     */
    public static function classes(): array
    {
        return self::CLASSES;
    }

    /**
     * One instance of each, with the one command that needs a value given it.
     *
     * `InitCommand` is the only command taking a constructor argument, and that
     * special case used to be written out in all three registries -- including
     * the two that only wanted to read a name off it and passed `''`.
     *
     * Constructing a command runs its `configure()`, which sets a name and
     * options and touches nothing else: no bootstrap, no filesystem. That is
     * what makes this safe to call while a framework container is still being
     * built.
     *
     * @return list<Command>
     */
    public static function instances(string $appRootDir): array
    {
        return array_map(
            static fn (string $class): Command => $class === InitCommand::class
                ? new InitCommand($appRootDir)
                : new $class(),
            self::CLASSES,
        );
    }
}
