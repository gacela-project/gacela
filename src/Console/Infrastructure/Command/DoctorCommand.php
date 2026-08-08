<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Doctor\Check\CacheStalenessCheck;
use Gacela\Console\Application\Doctor\Check\FilenameMismatchCheck;
use Gacela\Console\Application\Doctor\Check\ModuleHealthCheck;
use Gacela\Console\Application\Doctor\Check\SuffixMismatchCheck;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\ConsoleFacade;
use Gacela\Framework\Config\AppEnv;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\MergedConfigCache;
use Gacela\Framework\Gacela;
use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class DoctorCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('doctor')
            ->setDescription('Run environmental & wiring health checks for the current Gacela setup')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Restrict module-scoped checks to this namespace', '')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with a failure code on warnings too, for CI');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ConsoleSection::title($output, 'Gacela Doctor');

        $filter = ConsoleInput::argument($input, 'filter');
        $strict = $input->getOption('strict') === true;
        $checks = $this->buildChecks($filter);
        $worst = CheckStatus::Ok;

        foreach ($checks as $check) {
            $result = $check->run();
            $this->renderResult($result, $output);
            $worst = $this->worseOf($worst, $result->status);
        }

        $output->writeln('');
        ConsoleSection::separator($output);

        return match ($worst) {
            CheckStatus::Error => $this->finish($output, '<error>✗ Doctor found errors</error>', Command::FAILURE),
            CheckStatus::Warn => $this->finish(
                $output,
                '<fg=yellow>⚠ Doctor finished with warnings</>',
                $strict ? Command::FAILURE : Command::SUCCESS,
            ),
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
        $configFactory = $config->getFactory();
        $suffixTypes = $configFactory->createGacelaFileConfig()->getSuffixTypes();

        $checks = [
            new CacheStalenessCheck(
                $config->getCacheDir(),
                null,
                $config->getAppRootDir(),
                new MergedConfigCache($config->getCacheDir(), AppEnv::current(), $config->getAppRootDir()),
                $configFactory->createConfigLoader()->sourceFiles(),
            ),
            new SuffixMismatchCheck($modules, $suffixTypes),
            new FilenameMismatchCheck($modules),
        ];

        foreach (HealthCheckRegistry::createHealthChecker(Gacela::container())->checkAll()->getResults() as $moduleName => $status) {
            $checks[] = new ModuleHealthCheck($moduleName, $status);
        }

        return $checks;
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
        if ($a === CheckStatus::Error || $b === CheckStatus::Error) {
            return CheckStatus::Error;
        }

        if ($a === CheckStatus::Warn || $b === CheckStatus::Warn) {
            return CheckStatus::Warn;
        }

        return CheckStatus::Ok;
    }

    private function finish(OutputInterface $output, string $line, int $code): int
    {
        $output->writeln($line);
        $output->writeln('');
        return $code;
    }
}
