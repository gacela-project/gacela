<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FacadeAware\Module\UserInput\Command;

use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Feature\Framework\FacadeAware\Module\Facade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method Facade getFacade()
 */
final class TestHiCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write($this->getFacade()->sayHi());

        return self::SUCCESS;
    }
}
