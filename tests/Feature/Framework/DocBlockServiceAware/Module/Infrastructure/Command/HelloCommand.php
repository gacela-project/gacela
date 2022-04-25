<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Persistence\CustomHelloRepository getRepository()
 */
final class HelloCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write($this->getRepository()->findNameName());

        return self::SUCCESS;
    }
}
