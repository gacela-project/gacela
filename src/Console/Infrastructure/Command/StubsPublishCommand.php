<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\FileContent\StubFiles;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_values;
use function count;
use function implode;
use function in_array;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class StubsPublishCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const TEMPLATES = ['basic', 'service'];

    protected function configure(): void
    {
        $this->setName('stubs:publish')
            ->setDescription("Copy the scaffolder's templates into the project, so make:module generates your house style")
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Publish only one template set: basic or service')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite stubs the project already published')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report the stubs that would be published, and write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $template = ConsoleInput::option($input, 'template');
        if ($template !== '' && !in_array($template, self::TEMPLATES, true)) {
            $output->writeln(sprintf(
                '<error>Unknown template "%s". Use one of: %s</error>',
                $template,
                implode(', ', self::TEMPLATES),
            ));

            return self::FAILURE;
        }

        $dryRun = $input->getOption('dry-run') === true;
        $stubsDir = $this->getFacade()->stubsDir();
        $result = $this->getFacade()->publishStubs(
            $stubsDir,
            $this->stubsOf($template),
            $input->getOption('force') === true,
            $dryRun,
        );

        foreach ($result->written as $path) {
            $output->writeln($dryRun
                ? sprintf("> Would publish '%s'", $path)
                : sprintf('<fg=green>✓</> %s', $path));
        }

        if ($result->hasSkipped()) {
            $output->writeln('');
            foreach ($result->skipped as $path) {
                $output->writeln(sprintf('<error>✗ Already published:</error> %s', $path));
            }

            $output->writeln('<comment>Nothing was overwritten. Pass --force to replace them.</comment>');

            return self::FAILURE;
        }

        $output->writeln('');
        $output->writeln($dryRun
            ? sprintf(
                '<comment>Dry run: nothing was written (%d stub(s) would go into %s).</comment>',
                count($result->written),
                $stubsDir,
            )
            : sprintf('<info>%d stub(s) published into %s</info>', count($result->written), $stubsDir));

        return self::SUCCESS;
    }

    /**
     * @return list<string> the stub files to publish; empty means every one
     */
    private function stubsOf(string $template): array
    {
        return match ($template) {
            'basic' => array_values(StubFiles::basic()),
            'service' => array_values(StubFiles::service()),
            default => [],
        };
    }
}
