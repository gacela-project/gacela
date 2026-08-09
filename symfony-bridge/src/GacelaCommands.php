<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge;

use Gacela\Console\Infrastructure\Command\CacheClearCommand;
use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Console\Infrastructure\Command\DebugConfigCommand;
use Gacela\Console\Infrastructure\Command\DebugContainerCommand;
use Gacela\Console\Infrastructure\Command\DebugDependenciesCommand;
use Gacela\Console\Infrastructure\Command\DebugGraphCommand;
use Gacela\Console\Infrastructure\Command\DebugModuleCommand;
use Gacela\Console\Infrastructure\Command\DebugModulesCommand;
use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Console\Infrastructure\Command\InitCommand;
use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Console\Infrastructure\Command\MakeFileCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Console\Infrastructure\Command\ProfileReportCommand;
use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Gacela's commands and the names they answer to.
 *
 * The names are read off the commands themselves rather than written down
 * again: a renamed command would otherwise be registered under the old name
 * here and silently stop matching what `vendor/bin/gacela` calls it.
 *
 * @internal
 */
final class GacelaCommands
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
        CacheWarmCommand::class,
        CacheClearCommand::class,
        ValidateConfigCommand::class,
        ProfileReportCommand::class,
        DoctorCommand::class,
        InitCommand::class,
    ];

    /**
     * Constructing a command runs its `configure()`, which sets a name and
     * options and touches nothing else -- no bootstrap, no filesystem -- so it
     * is safe while Symfony's container is still being compiled.
     *
     * @return array<class-string<Command>, string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (self::CLASSES as $class) {
            $command = $class === InitCommand::class ? new InitCommand('') : new $class();
            $names[$class] = (string)$command->getName();
        }

        return $names;
    }
}
