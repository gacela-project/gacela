<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure;

use Gacela\Console\ConsoleFactory;
use Gacela\Framework\DocBlockResolverAwareTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * @method ConsoleFactory getFactory()
 */
final class ConsoleBootstrap extends Application
{
    use DocBlockResolverAwareTrait;

    /**
     * @return array<string,Command>
     *
     * @psalm-suppress MixedReturnTypeCoercion,PossiblyNullArrayOffset
     */
    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();

        foreach ($this->getFactory()->getConsoleCommands() as $command) {
            $commands[$command->getName()] = $command;
        }

        return $commands;
    }
}
