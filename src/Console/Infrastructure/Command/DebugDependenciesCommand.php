<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Framework\Gacela;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function class_exists;
use function interface_exists;
use function is_callable;
use function is_object;
use function sprintf;
use function str_repeat;
use function var_export;

final class DebugDependenciesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('debug:dependencies')
            ->setDescription('Show the constructor parameters of a class and their resolvability through the container')
            ->setHelp($this->getHelpText())
            ->addArgument('class', InputArgument::REQUIRED, 'Fully qualified class name to inspect');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $className */
        $className = $input->getArgument('class');

        if (!class_exists($className) && !interface_exists($className)) {
            $output->writeln(sprintf('<error>Class "%s" does not exist</error>', $className));
            return Command::FAILURE;
        }

        $reflection = new ReflectionClass($className);

        if ($reflection->isInterface()) {
            $output->writeln(sprintf('<error>"%s" is an interface — pass a concrete class instead</error>', $className));
            return Command::FAILURE;
        }

        if ($reflection->isAbstract()) {
            $output->writeln(sprintf('<error>"%s" is abstract — pass a concrete class instead</error>', $className));
            return Command::FAILURE;
        }

        $this->writeHeader($output, $className);

        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $message = $constructor === null ? 'No constructor' : 'Constructor takes no parameters';
            $output->writeln(sprintf('  <fg=cyan>%s</>', $message));
            $output->writeln('');
            return Command::SUCCESS;
        }

        $bindings = $this->containerBindings();

        $resolvable = 0;
        $unresolvable = 0;

        foreach ($constructor->getParameters() as $parameter) {
            $description = $this->describeParameter($parameter, $bindings);
            $output->writeln('  ' . $description['line']);

            if ($description['resolvable']) {
                ++$resolvable;
            } else {
                ++$unresolvable;
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('<fg=cyan>Resolvable:</>   %d', $resolvable));
        $output->writeln(sprintf('<fg=cyan>Unresolvable:</> %d', $unresolvable));
        $output->writeln('');

        if ($unresolvable > 0) {
            $output->writeln('<comment>Unresolvable parameters need an explicit binding or default value.</comment>');
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function writeHeader(OutputInterface $output, string $className): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<info>Constructor dependencies for %s</info>', $className));
        $output->writeln('<info>' . str_repeat('=', 60) . '</info>');
        $output->writeln('');
    }

    /**
     * @return array<class-string, class-string|callable|object>
     */
    private function containerBindings(): array
    {
        try {
            return Gacela::container()->getBindings();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<class-string, class-string|callable|object> $bindings
     *
     * @return array{line: string, resolvable: bool}
     */
    private function describeParameter(ReflectionParameter $parameter, array $bindings): array
    {
        $name = '$' . $parameter->getName();
        $typeLabel = $this->renderType($parameter);
        $status = $this->resolveStatus($parameter, $bindings);

        $line = sprintf(
            '%s %s %s %s',
            $status['resolvable'] ? '<fg=green>✓</>' : '<fg=red>✗</>',
            $name,
            $typeLabel,
            $status['detail'],
        );

        return ['line' => $line, 'resolvable' => $status['resolvable']];
    }

    private function renderType(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        if ($type === null) {
            return '<fg=yellow>mixed</>';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
            return $name;
        }

        return (string) $type;
    }

    /**
     * @param array<class-string, class-string|callable|object> $bindings
     *
     * @return array{resolvable: bool, detail: string}
     */
    private function resolveStatus(ReflectionParameter $parameter, array $bindings): array
    {
        $type = $parameter->getType();

        if ($type === null) {
            return $parameter->isDefaultValueAvailable()
                ? ['resolvable' => true, 'detail' => $this->defaultDetail($parameter)]
                : ['resolvable' => false, 'detail' => '<fg=red>no type hint and no default</>'];
        }

        if (!$type instanceof ReflectionNamedType) {
            return ['resolvable' => false, 'detail' => '<fg=red>union/intersection types not inspected</>'];
        }

        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return ['resolvable' => true, 'detail' => $this->defaultDetail($parameter)];
            }

            return ['resolvable' => false, 'detail' => '<fg=red>scalar without default</>'];
        }

        if (isset($bindings[$typeName])) {
            return ['resolvable' => true, 'detail' => sprintf('(bound -> %s)', $this->renderBindingTarget($bindings[$typeName]))];
        }

        if (class_exists($typeName)) {
            return ['resolvable' => true, 'detail' => '(autowirable)'];
        }

        if (interface_exists($typeName)) {
            return ['resolvable' => false, 'detail' => '<fg=red>interface, no binding</>'];
        }

        return ['resolvable' => false, 'detail' => '<fg=red>type does not exist</>'];
    }

    private function defaultDetail(ReflectionParameter $parameter): string
    {
        /** @var mixed $default */
        $default = $parameter->getDefaultValue();
        return sprintf('= %s', var_export($default, true));
    }

    /**
     * @param class-string|callable|object $target
     */
    private function renderBindingTarget(mixed $target): string
    {
        if (is_object($target)) {
            return $target::class . ' instance';
        }

        if (is_callable($target)) {
            return 'callable';
        }

        return $target;
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
Inspect the constructor signature of a class and report whether each parameter
can be resolved through the Gacela container.

<info>Resolution categories:</info>
  <fg=green>✓ bound</fg=green>        a binding in gacela.php maps the type to a concrete implementation
  <fg=green>✓ autowirable</fg=green>  concrete class exists and will be constructed automatically
  <fg=green>✓ default</fg=green>      the parameter has a default value
  <fg=red>✗ scalar</fg=red>       built-in type (string, int, ...) with no default
  <fg=red>✗ interface</fg=red>    interface type with no binding
  <fg=red>✗ missing</fg=red>      type does not exist

<info>Examples:</info>
  bin/gacela debug:dependencies "App\MyModule\MyFactory"
HELP;
    }
}
