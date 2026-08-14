<?php

declare(strict_types=1);

namespace Gacela\LaravelBridge;

use Gacela\Console\Infrastructure\Command\CommandCatalog;
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
    /**
     * Read off {@see CommandCatalog}, the one list, rather than a copy of it
     * kept here. Constructing a command is safe while the provider is still registering:
     * see the catalog for why.
     *
     * @return array<class-string<Command>, string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (CommandCatalog::instances('') as $command) {
            $names[$command::class] = (string)$command->getName();
        }

        return $names;
    }
}
