<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Persistence\CustomHelloRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method CustomHelloRepository getRepository()
 * @method RepositoryInSameNamespace getRepositoryInSameNamespace()
 */
final class HelloCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->getRepository()->findNameById(1));
        $output->write($this->getRepositoryInSameNamespace()->findNameById(2));

        return self::SUCCESS;
    }
}
