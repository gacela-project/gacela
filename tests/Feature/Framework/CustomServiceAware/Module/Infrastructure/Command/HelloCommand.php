<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Command;

use Gacela\Framework\CustomServicesResolverAwareTrait;
use GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Persistence\CustomHelloRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method CustomHelloRepository repository()
 */
final class HelloCommand extends Command
{
    use CustomServicesResolverAwareTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write($this->repository()->findNameName());

        return self::SUCCESS;
    }

    protected function servicesMapping(): array
    {
        return [
            'repository' => CustomHelloRepository::class,
        ];
    }
}
