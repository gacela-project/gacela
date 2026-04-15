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
use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\Health\HealthLevel;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function is_scalar;
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

        $checks = [
            new CacheStalenessCheck($config->getCacheDir()),
            new SuffixMismatchCheck($modules, $suffixTypes),
        ];

        foreach (HealthCheckRegistry::createHealthChecker()->checkAll()->getResults() as $moduleName => $status) {
            $checks[] = $this->toHealthCheck($moduleName, $status);
        }

        return $checks;
    }

    private function toHealthCheck(string $moduleName, HealthStatus $status): HealthCheck
    {
        return new class($moduleName, $status) implements HealthCheck {
            public function __construct(
                private readonly string $moduleName,
                private readonly HealthStatus $status,
            ) {
            }

            public function name(): string
            {
                return $this->moduleName;
            }

            public function run(): CheckResult
            {
                $title = sprintf('module health: %s', $this->moduleName);
                $detail = $this->status->message;
                $metadata = $this->formatMetadata();

                return match ($this->status->level) {
                    HealthLevel::HEALTHY => CheckResult::ok($title, $detail),
                    HealthLevel::DEGRADED => CheckResult::warn($title, $metadata === [] ? [$detail] : [$detail, ...$metadata]),
                    HealthLevel::UNHEALTHY => CheckResult::error($title, $metadata === [] ? [$detail] : [$detail, ...$metadata]),
                };
            }

            /**
             * @return list<string>
             */
            private function formatMetadata(): array
            {
                $lines = [];
                /** @var mixed $value */
                foreach ($this->status->metadata as $key => $value) {
                    $lines[] = sprintf('%s: %s', $key, is_scalar($value) ? (string) $value : get_debug_type($value));
                }

                return $lines;
            }
        };
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
