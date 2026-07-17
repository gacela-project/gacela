<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure;

use Gacela\Console\ConsoleFactory;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * @method ConsoleFactory getFactory()
 */
final class ConsoleBootstrap extends Application
{
    use ServiceResolverAwareTrait;

    /**
     * @return array<array-key,Command>
     */
    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();

        foreach ($this->getFactory()->getConsoleCommands() as $command) {
            $name = $command->getName();
            if ($name !== null) {
                $commands[$name] = $command;
            }
        }

        return $commands;
    }
}
