<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Debug\ConstructorInspection;
use Gacela\Console\Application\Debug\ConstructorInspector;
use Gacela\Console\Application\Debug\ParameterInspection;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function class_exists;
use function sprintf;
use function str_repeat;
use function strlen;

/**
 * @method ConsoleFacade getFacade()
 */
final class DebugModulesCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('debug:modules')
            ->setDescription('Show dependency resolvability of every Gacela module pillar (Facade, Factory, Config, Provider)')
            ->setHelp($this->getHelpText())
            ->addArgument('filter', InputArgument::OPTIONAL, 'Restrict output to modules whose name matches this substring', '')
            ->addOption('detail', 'd', InputOption::VALUE_NONE, 'Include every parameter, not just unresolvable ones');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $filter */
        $filter = $input->getArgument('filter');
        $detail = (bool) $input->getOption('detail');

        try {
            $modules = $this->getFacade()->findAllAppModules($filter);
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>Could not discover modules: %s</error>', $throwable->getMessage()));
            return Command::FAILURE;
        }

        $this->writeHeader($output, $filter);

        if ($modules === []) {
            $output->writeln($filter === ''
                ? '  <comment>No modules discovered.</comment>'
                : sprintf('  <comment>No modules match filter "%s".</comment>', $filter));
            $output->writeln('');
            return Command::SUCCESS;
        }

        $inspector = new ConstructorInspector();
        $moduleCount = 0;
        $pillarCount = 0;
        $unresolvableTotal = 0;

        foreach ($modules as $module) {
            $pillars = $this->existingPillarClasses($module);
            if ($pillars === []) {
                continue;
            }

            ++$moduleCount;
            $output->writeln(sprintf('  <fg=green>%s</>', $module->fullModuleName()));

            foreach ($pillars as $pillarClass) {
                ++$pillarCount;
                $inspection = $inspector->inspect($pillarClass);
                $unresolvableTotal += $inspection->unresolvableCount();

                $this->writePillar($output, $inspection, $detail);
            }

            $output->writeln('');
        }

        $this->writeSummary($output, $moduleCount, $pillarCount, $unresolvableTotal);

        return Command::SUCCESS;
    }

    /**
     * Combine what discovery reported with conventional-name probes so that a
     * pillar class present on disk is still inspected even when its resolver
     * would fail to instantiate it (e.g. a factory whose ctor takes an unbound
     * dependency — exactly the failure mode we want to surface).
     *
     * @return list<class-string>
     */
    private function existingPillarClasses(AppModule $module): array
    {
        $pillars = [$module->facadeClass()];

        foreach ($this->pillarsBySuffix($module) as $pillar) {
            if ($pillar === null || !class_exists($pillar)) {
                continue;
            }
            $pillars[] = $pillar;
        }

        return array_values(array_unique($pillars));
    }

    /**
     * @return array<string, ?string>
     */
    private function pillarsBySuffix(AppModule $module): array
    {
        $facade = $module->facadeClass();

        return [
            'Factory' => $module->factoryClass() ?? $this->classByConvention($facade, 'Factory'),
            'Config' => $module->configClass() ?? $this->classByConvention($facade, 'Config'),
            'Provider' => $module->providerClass() ?? $this->classByConvention($facade, 'Provider'),
        ];
    }

    private function classByConvention(string $facadeClass, string $suffix): ?string
    {
        if (!str_ends_with($facadeClass, 'Facade')) {
            return null;
        }

        $candidate = substr($facadeClass, 0, -strlen('Facade')) . $suffix;

        return class_exists($candidate) ? $candidate : null;
    }

    private function writeHeader(OutputInterface $output, string $filter): void
    {
        $title = $filter === ''
            ? 'Gacela modules: constructor resolvability'
            : sprintf('Gacela modules matching "%s"', $filter);

        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info>', $title));
        $output->writeln('<info>' . str_repeat('=', 60) . '</info>');
        $output->writeln('');
    }

    private function writePillar(OutputInterface $output, ConstructorInspection $inspection, bool $detail): void
    {
        if (!$inspection->hasConstructor) {
            $output->writeln(sprintf('    <fg=green>✓</> %s <fg=cyan>(no constructor)</>', $inspection->className));
            return;
        }

        if ($inspection->parameters === []) {
            $output->writeln(sprintf('    <fg=green>✓</> %s <fg=cyan>(constructor takes no parameters)</>', $inspection->className));
            return;
        }

        $marker = $inspection->isFullyResolvable() ? '<fg=green>✓</>' : '<fg=red>✗</>';

        $output->writeln(sprintf(
            '    %s %s <fg=cyan>(%d resolvable, %d unresolvable)</>',
            $marker,
            $inspection->className,
            $inspection->resolvableCount(),
            $inspection->unresolvableCount(),
        ));

        $parametersToList = $detail
            ? $inspection->parameters
            : $inspection->unresolvableParameters();

        foreach ($parametersToList as $parameter) {
            $output->writeln('       ' . $this->formatParameter($parameter));
        }
    }

    private function formatParameter(ParameterInspection $parameter): string
    {
        $marker = $parameter->isResolvable() ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $detail = $parameter->isResolvable()
            ? $parameter->detail
            : sprintf('<fg=red>%s</>', $parameter->detail);

        return sprintf('%s %s %s (%s)', $marker, $parameter->name, $parameter->renderedType, $detail);
    }

    private function writeSummary(
        OutputInterface $output,
        int $moduleCount,
        int $pillarCount,
        int $unresolvableTotal,
    ): void {
        $output->writeln(sprintf(
            '<fg=cyan>Summary:</> %d %s, %d %s inspected, %d unresolvable %s',
            $moduleCount,
            $moduleCount === 1 ? 'module' : 'modules',
            $pillarCount,
            $pillarCount === 1 ? 'pillar' : 'pillars',
            $unresolvableTotal,
            $unresolvableTotal === 1 ? 'parameter' : 'parameters',
        ));

        if ($unresolvableTotal > 0) {
            $output->writeln('<comment>Run bin/gacela debug:dependencies \<class> for a per-class view.</comment>');
        }

        $output->writeln('');
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
Walks every discovered Gacela module and inspects the constructor of each
pillar (Facade, Factory, Config, Provider) through the container, flagging
any parameter that cannot be resolved.

<info>Examples:</info>
  # Inspect every module
  bin/gacela debug:modules

  # Limit to modules whose name contains "Checkout"
  bin/gacela debug:modules Checkout

  # Show every parameter, not just the unresolvable ones
  bin/gacela debug:modules --detail

<comment>Complements:</comment>
  bin/gacela list:modules          list modules and their pillars
  bin/gacela debug:dependencies    deep-dive into one class
HELP;
    }
}
