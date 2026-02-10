<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ServiceResolverAwareTrait;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Question\ChoiceQuestion;

use function count;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
final class ExploreCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('explore')
            ->setDescription('Interactively explore modules and their details');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Module Explorer</info>');
        $output->writeln('<comment>Interactive module exploration tool</comment>');
        $output->writeln('');

        $modules = $this->getFacade()->findAllAppModules();

        if (count($modules) === 0) {
            $output->writeln('<comment>No modules found.</comment>');

            return self::SUCCESS;
        }

        $this->displayModuleList($modules, $output);

        // Interactive selection
        $helper = $this->getHelper('question');
        $moduleNames = array_map(static fn (AppModule $m): string => $m->fullModuleName(), $modules);
        $moduleNames[] = 'Exit';

        $question = new ChoiceQuestion(
            "\nSelect a module to explore (or Exit):",
            $moduleNames,
            0,
        );

        $selectedName = $helper->ask($input, $output, $question);

        if ($selectedName === 'Exit') {
            $output->writeln('<info>Goodbye!</info>');

            return self::SUCCESS;
        }

        // Find selected module
        foreach ($modules as $module) {
            if ($module->fullModuleName() === $selectedName) {
                $this->displayModuleDetails($module, $output);
                break;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param list<AppModule> $modules
     */
    private function displayModuleList(array $modules, OutputInterface $output): void
    {
        $output->writeln(sprintf('<info>Found %d module(s):</info>', count($modules)));
        $output->writeln('');

        $table = new Table($output);
        $table->setStyle('box');
        $table->setHeaders(['Module', 'Facade', 'Factory', 'Config', 'Provider']);

        foreach ($modules as $module) {
            $table->addRow([
                $module->moduleName(),
                '✓',
                $module->factoryClass() !== null ? '✓' : '✗',
                $module->configClass() !== null ? '✓' : '✗',
                $module->providerClass() !== null ? '✓' : '✗',
            ]);
        }

        $table->render();
    }

    private function displayModuleDetails(AppModule $module, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>═══════════════════════════════════════════════════════════════</info>');
        $output->writeln(sprintf('<info>Module: %s</info>', $module->fullModuleName()));
        $output->writeln('<info>═══════════════════════════════════════════════════════════════</info>');
        $output->writeln('');

        // Module structure
        $output->writeln('<comment>Module Structure:</comment>');
        $output->writeln(sprintf('  Facade:   %s', $module->facadeClass()));

        if ($module->factoryClass() !== null) {
            $output->writeln(sprintf('  Factory:  %s', $module->factoryClass()));
        }

        if ($module->configClass() !== null) {
            $output->writeln(sprintf('  Config:   %s', $module->configClass()));
        }

        if ($module->providerClass() !== null) {
            $output->writeln(sprintf('  Provider: %s', $module->providerClass()));
        }

        $output->writeln('');

        // Public methods
        $this->displayPublicMethods($module, $output);

        // Dependencies
        $this->displayDependencies($module, $output);
    }

    private function displayPublicMethods(AppModule $module, OutputInterface $output): void
    {
        $output->writeln('<comment>Public Methods:</comment>');

        $facadeClass = $module->facadeClass();

        if (!class_exists($facadeClass)) {
            $output->writeln('  <fg=yellow>Class not loaded</>');

            return;
        }

        $reflection = new ReflectionClass($facadeClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $count = 0;

        foreach ($methods as $method) {
            // Skip inherited methods
            if ($method->getDeclaringClass()->getName() !== $facadeClass) {
                continue;
            }

            $params = [];
            foreach ($method->getParameters() as $param) {
                $paramType = $param->getType() !== null ? $param->getType() . ' ' : '';
                $params[] = sprintf('%s$%s', $paramType, $param->getName());
            }

            $returnType = $method->getReturnType() !== null ? ': ' . $method->getReturnType() : '';
            $output->writeln(sprintf(
                '  • %s(%s)%s',
                $method->getName(),
                implode(', ', $params),
                $returnType,
            ));
            ++$count;
        }

        if ($count === 0) {
            $output->writeln('  <fg=yellow>No public methods defined</>');
        }

        $output->writeln('');
    }

    private function displayDependencies(AppModule $module, OutputInterface $output): void
    {
        $output->writeln('<comment>Dependencies:</comment>');

        $modules = $this->getFacade()->findAllAppModules();
        $dependencies = $this->getFacade()->analyzeModuleDependencies($modules);

        foreach ($dependencies as $dep) {
            if ($dep->moduleName() === $module->fullModuleName()) {
                if (count($dep->dependencies()) === 0) {
                    $output->writeln('  <fg=green>No dependencies</>');
                } else {
                    foreach ($dep->dependencies() as $dependency) {
                        $output->writeln(sprintf('  → %s', $dependency));
                    }
                }
                break;
            }
        }

        $output->writeln('');
    }
}
