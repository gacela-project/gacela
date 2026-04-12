<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Doctor\Check\CacheStalenessCheck;
use Gacela\Console\Application\Doctor\Check\SuffixMismatchCheck;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\ConsoleFacade;
use Gacela\Framework\Config\Config;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
final class DoctorCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('doctor')
            ->setDescription('Run environmental & wiring health checks for the current Gacela setup')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Restrict module-scoped checks to this namespace', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>Gacela Doctor</info>');
        $output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));
        $output->writeln('');

        $filter = (string) $input->getArgument('filter');
        $checks = $this->buildChecks($filter);
        $worst = CheckStatus::Ok;

        foreach ($checks as $check) {
            $result = $check->run();
            $this->renderResult($result, $output);
            $worst = $this->worseOf($worst, $result->status);
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));

        return match ($worst) {
            CheckStatus::Error => $this->finish($output, '<error>✗ Doctor found errors</error>', Command::FAILURE),
            CheckStatus::Warn => $this->finish($output, '<fg=yellow>⚠ Doctor finished with warnings</>', Command::SUCCESS),
            CheckStatus::Ok => $this->finish($output, '<fg=green>✓ All checks passed</>', Command::SUCCESS),
        };
    }

    /**
     * @return list<HealthCheck>
     */
    private function buildChecks(string $filter): array
    {
        $config = Config::getInstance();
        $modules = $this->getFacade()->findAllAppModules($filter);
        $suffixTypes = $config->getFactory()->createGacelaFileConfig()->getSuffixTypes();

        return [
            new CacheStalenessCheck($config->getCacheDir()),
            new SuffixMismatchCheck($modules, $suffixTypes),
        ];
    }

    private function renderResult(CheckResult $result, OutputInterface $output): void
    {
        [$marker, $tag] = match ($result->status) {
            CheckStatus::Ok => ['✓', 'fg=green'],
            CheckStatus::Warn => ['⚠', 'fg=yellow'],
            CheckStatus::Error => ['✗', 'error'],
        };

        $output->writeln(sprintf('<%s>%s %s</>', $tag, $marker, $result->title));

        foreach ($result->details as $detail) {
            $output->writeln('    ' . $detail);
        }

        if ($result->remediation !== '') {
            $output->writeln(sprintf('    <comment>→ %s</comment>', $result->remediation));
        }

        $output->writeln('');
    }

    private function worseOf(CheckStatus $a, CheckStatus $b): CheckStatus
    {
        $rank = static fn (CheckStatus $s): int => match ($s) {
            CheckStatus::Ok => 0,
            CheckStatus::Warn => 1,
            CheckStatus::Error => 2,
        };
        return $rank($a) >= $rank($b) ? $a : $b;
    }

    private function finish(OutputInterface $output, string $line, int $code): int
    {
        $output->writeln($line);
        $output->writeln('');
        return $code;
    }
}
