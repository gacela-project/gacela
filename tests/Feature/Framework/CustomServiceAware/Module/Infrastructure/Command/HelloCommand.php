<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Command;

use Gacela\Framework\CustomServicesResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Persistence\CustomHelloRepository getRepository()
 */
final class HelloCommand extends Command
{
    use CustomServicesResolverAwareTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write($this->getRepository()->findNameName());

        return self::SUCCESS;
    }
}
