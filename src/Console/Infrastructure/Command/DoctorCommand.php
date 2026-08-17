<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Debug\EventCatalog;
use Gacela\Console\Application\Debug\PackageDiscoveryReport;
use Gacela\Console\Application\Doctor\Check\CacheableStorageCheck;
use Gacela\Console\Application\Doctor\Check\CacheStalenessCheck;
use Gacela\Console\Application\Doctor\Check\CacheWritabilityCheck;
use Gacela\Console\Application\Doctor\Check\ConfigEnvironmentLayerCheck;
use Gacela\Console\Application\Doctor\Check\ConfigSchemaCheck;
use Gacela\Console\Application\Doctor\Check\ConfigSourceCheck;
use Gacela\Console\Application\Doctor\Check\DiscoveredPackagesCheck;
use Gacela\Console\Application\Doctor\Check\DuplicateProvidedIdCheck;
use Gacela\Console\Application\Doctor\Check\EventListenerTargetCheck;
use Gacela\Console\Application\Doctor\Check\FilenameMismatchCheck;
use Gacela\Console\Application\Doctor\Check\HandlerRegistryCheck;
use Gacela\Console\Application\Doctor\Check\IdeMetadataStalenessCheck;
use Gacela\Console\Application\Doctor\Check\ModuleHealthCheck;
use Gacela\Console\Application\Doctor\Check\ModulePathCheck;
use Gacela\Console\Application\Doctor\Check\PackageManifestCheck;
use Gacela\Console\Application\Doctor\Check\PluginStackCheck;
use Gacela\Console\Application\Doctor\Check\ServiceExtensionTargetCheck;
use Gacela\Console\Application\Doctor\Check\StubHealthCheck;
use Gacela\Console\Application\Doctor\Check\SuffixMismatchCheck;
use Gacela\Console\Application\Doctor\Check\TaggedServiceTargetCheck;
use Gacela\Console\Application\Doctor\Check\UndiscoveredFacadeCheck;
use Gacela\Console\Application\Doctor\Check\UnresolvedPillarFileCheck;
use Gacela\Console\Application\Doctor\Check\UnusableProvidesCheck;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
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

    private const FORMATS = ['text', 'json'];

    protected function configure(): void
    {
        $this->setName('doctor')
            ->setDescription('Run environmental & wiring health checks for the current Gacela setup')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Restrict module-scoped checks to this namespace', '')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with a failure code on warnings too, for CI')
            ->addOption('only-problems', null, InputOption::VALUE_NONE, 'Report only the checks that found something')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text|json', 'text')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Shorthand for --format=json')
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = ConsoleInput::argument($input, 'filter');
        $strict = $input->getOption('strict') === true;
        $onlyProblems = $input->getOption('only-problems') === true;

        $format = ConsoleInput::format($input);
        $unknownFormat = ConsoleChoice::unknown('format', $format, self::FORMATS);
        if ($unknownFormat !== null) {
            $output->writeln($unknownFormat);

            return Command::FAILURE;
        }

        // Discovered once, here rather than inside buildChecks(), because the
        // report says how many modules the run actually inspected and a second
        // call would walk the project again to answer it.
        $modules = $this->getFacade()->findAllAppModules($filter);

        if ($format === 'json') {
            return $this->renderJson(
                $output,
                $this->buildChecks($modules),
                $strict,
                $onlyProblems,
                $filter,
                count($modules),
            );
        }

        ConsoleSection::title($output, 'Gacela Doctor');

        // Every check below works from the modules discovery returned, so the
        // paths it walked bound all of them at once. `appModulePaths` narrowing
        // the scan is invisible in a report of twenty ticks otherwise.
        $output->writeln(sprintf(
            'Scanned: %s',
            implode(', ', $this->getFacade()->scannedModulePaths()),
        ));

        // Dropping these silently is the same failure as calling them scanned,
        // one report further along: every check below ran without them, and a
        // screen of ticks is exactly what makes that look fine.
        $unscanned = $this->getFacade()->unscannedModulePaths();
        if ($unscanned !== []) {
            $output->writeln(sprintf(
                '<comment>Not scanned, not a directory: %s</comment>',
                implode(', ', $unscanned),
            ));
        }

        // The filter narrows which modules get inspected, not which checks run,
        // so one matching nothing leaves every module-scoped check reporting
        // "no modules discovered" and the run ending in "All checks passed".
        // `Scanned:` cannot answer this: the paths were walked, and the filter
        // excluded what they found afterwards.
        if ($filter !== '') {
            $output->writeln($modules === []
                ? sprintf('<comment>Filter: %s — matched no modules</comment>', $filter)
                : sprintf('Filter: %s — %d module(s)', $filter, count($modules)));
        }

        $output->writeln('');

        $checks = $this->buildChecks($modules);
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
    private function renderJson(
        OutputInterface $output,
        array $checks,
        bool $strict,
        bool $onlyProblems,
        string $filter,
        int $moduleCount,
    ): int {
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
            'scanned' => $this->getFacade()->scannedModulePaths(),
            'unscanned' => $this->getFacade()->unscannedModulePaths(),
            'filter' => $filter,
            'modules' => $moduleCount,
            'checks' => $reported,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return match ($worst) {
            CheckStatus::Error => Command::FAILURE,
            CheckStatus::Warn => $strict ? Command::FAILURE : Command::SUCCESS,
            CheckStatus::Ok => Command::SUCCESS,
        };
    }

    /**
     * @param list<AppModule> $modules discovered once by the caller, which also reports on them
     *
     * @return list<HealthCheck>
     */
    private function buildChecks(array $modules): array
    {
        $config = Config::getInstance();
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
            // Ahead of every module-scoped check below, because it is about
            // the input all of them share: a path that scanned nothing makes
            // each of them pass over less than the reader thinks it did.
            new ModulePathCheck(
                $this->getFacade()->scannedModulePaths(),
                $this->getFacade()->unscannedModulePaths(),
            ),
            new SuffixMismatchCheck($modules, $suffixTypes),
            new FilenameMismatchCheck($modules, $suffixTypes),
            new UndiscoveredFacadeCheck($this->getFacade()->findUndiscoveredFacadeFiles()),
            new UnresolvedPillarFileCheck($modules, $suffixTypes),
            new CacheableStorageCheck($modules, CacheableConfig::hasUserSuppliedStorage()),
            new PluginStackCheck($config->getSetupGacela()->getPluginStacks()),
            new HandlerRegistryCheck($config->getSetupGacela()->getHandlerRegistries()),
            new DuplicateProvidedIdCheck($modules),
            new UnusableProvidesCheck($modules),
            new ConfigSourceCheck(
                $configLoader->patternsMatchingNothing(),
                count($configLoader->declaredPatterns()),
            ),
            // Beside the check above because both are about what a declared
            // config path resolved to: that one reports a path loading nothing,
            // this one the files it matched and the base layer does not read.
            new ConfigEnvironmentLayerCheck(
                $configLoader->excludedEnvironmentLayers(),
                $config->getSetupGacela()->getConfigDimensions(),
            ),
            new ConfigSchemaCheck($config->configSchema(), $config->getAllValues()),
            new StubHealthCheck($stubsDir, StubHealthCheck::readPublished($stubsDir, $declaredKinds), $declaredKinds),
            new EventListenerTargetCheck(
                $this->specificListenerTargets(),
                $this->genericListenerCount(),
                $this->eventDispatcherCanBeBuilt(),
                $this->knownEventClasses(),
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
            new DiscoveredPackagesCheck($this->packageDiscoveryReport()),
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
     * The opt-out list comes off the concrete setup, for the same reason the
     * listener map below does.
     */
    private function packageDiscoveryReport(): PackageDiscoveryReport
    {
        $setup = Config::getInstance()->getSetupGacela();

        return PackageDiscoveryReport::capture(
            Config::getInstance()->getAppRootDir(),
            $setup instanceof SetupGacela ? $setup->getDontDiscover() : [],
        );
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
     * The events a listener target can be judged against: the framework's
     * catalog and the ones this project declares.
     *
     * Skipped entirely when no target is registered. The project half is a walk
     * of the application's files, and `doctor` should not pay for it to answer a
     * question nobody asked -- the check returns early on an empty target list
     * anyway.
     *
     * @return list<class-string>
     */
    private function knownEventClasses(): array
    {
        if ($this->specificListenerTargets() === []) {
            return [];
        }

        return [
            ...(new EventCatalog())->eventClasses(),
            ...$this->getFacade()->findProjectEventClasses(),
        ];
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

    /**
     * Deliberately free of a check list or a count: both would go stale the
     * next time one is added, and a run already names every check it made.
     * What is not visible from a run is the verdict model, which is what this
     * explains.
     */
    private function getHelpText(): string
    {
        return <<<'HELP'
Runs every built-in health check against the current setup, plus any registered
with `GacelaConfig::addHealthCheck()`. Each check names what it found and how to
fix it; a run lists them all, so this help does not repeat them.

<info>Errors and warnings are different claims:</info>
  <error>Errors</error>   something is broken now -- a pillar file nothing can load, a
           binding whose class does not exist. These always fail the run.
  <fg=yellow>Warnings</fg=yellow> something is inert or will not do what it looks like it does --
           a listener nothing can dispatch to, a `#[Cacheable]` method on
           storage that dies with the process. These pass by default,
           because several of them are correct in some deployments.

<info>Examples:</info>
  # Report everything
  bin/gacela doctor

  # Fail the build on warnings too -- the usual CI setting
  bin/gacela doctor --strict

  # Only what found something, for a short report
  bin/gacela doctor --only-problems

  # Say which check failed, for a job that acts on it
  bin/gacela doctor --json

  # Narrow the module-scoped checks to one namespace
  bin/gacela doctor App/Checkout

<comment>The filter narrows which modules get inspected, not which checks run.</comment>
Every check still reports; the ones about configuration, caches and manifests
describe the whole project and ignore it.

<comment>Complements:</comment>
  bin/gacela validate:config       bindings, dependency cycles, config schema
  bin/gacela debug:modules --check whether each pillar's constructor resolves
HELP;
    }
}
