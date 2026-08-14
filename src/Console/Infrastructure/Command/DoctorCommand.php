<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Doctor\Check\CacheableStorageCheck;
use Gacela\Console\Application\Doctor\Check\CacheStalenessCheck;
use Gacela\Console\Application\Doctor\Check\CacheWritabilityCheck;
use Gacela\Console\Application\Doctor\Check\ConfigSchemaCheck;
use Gacela\Console\Application\Doctor\Check\ConfigSourceCheck;
use Gacela\Console\Application\Doctor\Check\DuplicateProvidedIdCheck;
use Gacela\Console\Application\Doctor\Check\EventListenerTargetCheck;
use Gacela\Console\Application\Doctor\Check\FilenameMismatchCheck;
use Gacela\Console\Application\Doctor\Check\IdeMetadataStalenessCheck;
use Gacela\Console\Application\Doctor\Check\ModuleHealthCheck;
use Gacela\Console\Application\Doctor\Check\PackageManifestCheck;
use Gacela\Console\Application\Doctor\Check\PluginStackCheck;
use Gacela\Console\Application\Doctor\Check\ServiceExtensionTargetCheck;
use Gacela\Console\Application\Doctor\Check\StubHealthCheck;
use Gacela\Console\Application\Doctor\Check\SuffixMismatchCheck;
use Gacela\Console\Application\Doctor\Check\TaggedServiceTargetCheck;
use Gacela\Console\Application\Doctor\Check\UndiscoveredFacadeCheck;
use Gacela\Console\Application\Doctor\Check\UnreachableProvidesCheck;
use Gacela\Console\Application\Doctor\Check\UnresolvedPillarFileCheck;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\IdeMeta\IdeMetadataResult;
use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\ClassResolver\ClassResolverCache;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

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
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with a failure code on warnings too, for CI')
            ->addOption('only-problems', null, InputOption::VALUE_NONE, 'Report only the checks that found something')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text|json', 'text')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Shorthand for --format=json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = ConsoleInput::argument($input, 'filter');
        $strict = $input->getOption('strict') === true;
        $onlyProblems = $input->getOption('only-problems') === true;

        if (ConsoleInput::format($input) === 'json') {
            return $this->renderJson($output, $this->buildChecks($filter), $strict, $onlyProblems);
        }

        ConsoleSection::title($output, 'Gacela Doctor');

        $checks = $this->buildChecks($filter);
        $worst = CheckStatus::Ok;
        $rendered = false;

        foreach ($checks as $check) {
            $result = $check->run();

            // A passing check is the bulk of the output and none of the news.
            // The summary below still reports, so a clean run says so rather
            // than printing nothing and leaving "did it run?" open.
            if (!$onlyProblems || $result->status !== CheckStatus::Ok) {
                $this->renderResult($result, $output);
                $rendered = true;
            }

            $worst = $this->worseOf($worst, $result->status);
        }

        // renderResult() already ends each block with a blank line, so this one
        // only separates the summary from *something*. With --only-problems and
        // nothing to report there is nothing to separate it from.
        if ($rendered) {
            $output->writeln('');
        }

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
     * The deploy gate's other half. `--strict` already answers "did anything
     * go wrong" with an exit code; a job that wants to say *which* check, and
     * repeat its remediation into a review comment, had to parse the prose.
     * `debug:graph --check --format=json` is here for the same reason.
     *
     * Every check is reported, including the passing ones, unless
     * `--only-problems` narrows it -- the flag means the same thing in both
     * formats. `status` is the string the enum already carries, so the values
     * are `ok`, `warn` and `error` rather than a second vocabulary.
     *
     * @param list<HealthCheck> $checks
     */
    private function renderJson(OutputInterface $output, array $checks, bool $strict, bool $onlyProblems): int
    {
        $worst = CheckStatus::Ok;
        $reported = [];

        foreach ($checks as $check) {
            $result = $check->run();
            $worst = $this->worseOf($worst, $result->status);

            if ($onlyProblems && $result->status === CheckStatus::Ok) {
                continue;
            }

            $reported[] = [
                'name' => $check->name(),
                'status' => $result->status->value,
                'details' => $result->details,
                'remediation' => $result->remediation,
            ];
        }

        $output->writeln(json_encode([
            'status' => $worst->value,
            'checks' => $reported,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return match ($worst) {
            CheckStatus::Error => Command::FAILURE,
            CheckStatus::Warn => $strict ? Command::FAILURE : Command::SUCCESS,
            CheckStatus::Ok => Command::SUCCESS,
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
        $gacelaFileConfig = $configFactory->createGacelaFileConfig();
        $configLoader = $configFactory->createConfigLoader();
        $suffixTypes = $gacelaFileConfig->getSuffixTypes();
        $stubsDir = $this->getFacade()->stubsDir();
        $declaredKinds = ResolvableTypes::declaredKinds();

        $checks = [
            new CacheStalenessCheck(
                $config->getCacheDir(),
                null,
                $config->getAppRootDir(),
                $config->mergedConfigCache(),
                $configLoader->sourceFiles(),
                ClassResolverCache::bootstrapFingerprint(),
            ),
            // Ahead of the staleness check on purpose: with nowhere to write,
            // there are no cache files to be stale and that check reports
            // "nothing to check" -- which is the symptom, not the cause.
            new CacheWritabilityCheck(
                $config->getSetupGacela()->isFileCacheEnabled(),
                $config->getCacheDir(),
            ),
            new SuffixMismatchCheck($modules, $suffixTypes),
            new FilenameMismatchCheck($modules, $suffixTypes),
            new UndiscoveredFacadeCheck($this->getFacade()->findUndiscoveredFacadeFiles()),
            new UnresolvedPillarFileCheck($modules, $suffixTypes),
            new CacheableStorageCheck($modules, CacheableConfig::hasUserSuppliedStorage()),
            new PluginStackCheck($config->getSetupGacela()->getPluginStacks()),
            new DuplicateProvidedIdCheck($modules),
            new UnreachableProvidesCheck($modules),
            new ConfigSourceCheck(
                $configLoader->patternsMatchingNothing(),
                count($configLoader->declaredPatterns()),
            ),
            new ConfigSchemaCheck($config->configSchema(), $config->getAllValues()),
            new StubHealthCheck($stubsDir, StubHealthCheck::readPublished($stubsDir, $declaredKinds), $declaredKinds),
            new EventListenerTargetCheck(
                $this->specificListenerTargets(),
                $this->genericListenerCount(),
                $this->eventDispatcherCanBeBuilt(),
            ),
            new ServiceExtensionTargetCheck(
                $modules,
                array_keys($config->getSetupGacela()->getServicesToExtend()),
                Gacela::container()->getRegisteredServices(),
                $this->providerScopedExtensionIds(),
            ),
            new TaggedServiceTargetCheck(
                $modules,
                $config->getSetupGacela()->getTags(),
                Gacela::container()->getRegisteredServices(),
            ),
            // Regenerated unfiltered, and lazily: the metadata file describes
            // the whole application, so comparing it against the modules a
            // namespace filter left behind would report every scoped run as
            // stale.
            new PackageManifestCheck($config->getAppRootDir()),
            new IdeMetadataStalenessCheck(
                $config->getAppRootDir(),
                fn (): IdeMetadataResult => $this->getFacade()->generateIdeMetadata(dryRun: true),
            ),
        ];

        foreach (HealthCheckRegistry::createHealthChecker(Gacela::container())->checkAll()->getResults() as $moduleName => $status) {
            $checks[] = new ModuleHealthCheck($moduleName, $status);
        }

        return $checks;
    }

    /**
     * Read off the concrete setup: the listener map is not part of
     * SetupGacelaInterface, and widening a public contract to reach a
     * diagnostic would be the tail wagging the dog. An implementation that is
     * not Gacela's own simply reports nothing here.
     *
     * @return list<class-string>
     */
    private function specificListenerTargets(): array
    {
        $setup = Config::getInstance()->getSetupGacela();

        if (!$setup instanceof SetupGacela) {
            return [];
        }

        /** @var list<class-string> $targets */
        $targets = array_keys($setup->getSpecificListeners() ?? []);

        return $targets;
    }

    /**
     * How many `registerGenericListener()` callables there are. They carry no
     * target, so a project whose only listeners are generic has an empty target
     * list and would otherwise look like one that registered nothing.
     */
    private function genericListenerCount(): int
    {
        $setup = Config::getInstance()->getSetupGacela();

        if (!$setup instanceof SetupGacela) {
            return 0;
        }

        return count($setup->getGenericListeners() ?? []);
    }

    /**
     * False when `disableEventListeners()` was called, so nothing registered can
     * run.
     *
     * Read through `canCreateEventDispatcher()`, which is already public,
     * rather than widening the setup to expose the flag for a diagnostic. It
     * also answers false when nothing is registered, which is why the check is
     * told how much *is* registered and only concludes "disabled" when both are
     * true.
     */
    private function eventDispatcherCanBeBuilt(): bool
    {
        $setup = Config::getInstance()->getSetupGacela();

        if (!$setup instanceof SetupGacela) {
            return true;
        }

        return $setup->canCreateEventDispatcher();
    }

    /**
     * Ids passed to `extendProviderService()`, grouped by the Provider each
     * names.
     *
     * Read off the concrete setup for the same reason as the listener map
     * above: it is not part of SetupGacelaInterface.
     *
     * @return array<class-string, list<string>>
     */
    private function providerScopedExtensionIds(): array
    {
        $setup = Config::getInstance()->getSetupGacela();

        if (!$setup instanceof SetupGacela) {
            return [];
        }

        $byProvider = [];

        foreach ($setup->getProviderServicesToExtend() as $providerClass => $byId) {
            $byProvider[$providerClass] = array_keys($byId);
        }

        return $byProvider;
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
