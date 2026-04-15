<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Debug\ConstructorInspection;
use Gacela\Console\Application\Debug\ConstructorInspector;
use Gacela\Console\Application\Debug\ParameterInspection;
use Gacela\Console\Application\Debug\ParameterStatus;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function class_exists;
use function count;
use function defined;
use function interface_exists;
use function is_array;
use function ltrim;
use function sprintf;
use function str_repeat;

final class DebugDependenciesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('debug:dependencies')
            ->setDescription('Show the constructor parameters of a class and their resolvability through the container')
            ->setHelp($this->getHelpText())
            ->addArgument('class', InputArgument::REQUIRED, 'Fully qualified class name or a path to a PHP file declaring the class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $argument */
        $argument = $input->getArgument('class');

        $className = $this->resolveClassName($argument, $output);
        if ($className === null) {
            return Command::FAILURE;
        }

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

        $inspection = (new ConstructorInspector())->inspect($className);

        $this->renderInspection($output, $inspection);

        return Command::SUCCESS;
    }

    private function renderInspection(OutputInterface $output, ConstructorInspection $inspection): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<info>Constructor dependencies for %s</info>', $inspection->className));
        $output->writeln('<info>' . str_repeat('=', 60) . '</info>');
        $output->writeln('');

        if (!$inspection->hasConstructor) {
            $output->writeln('  <fg=cyan>No constructor</>');
            $output->writeln('');
            return;
        }

        if ($inspection->parameters === []) {
            $output->writeln('  <fg=cyan>Constructor takes no parameters</>');
            $output->writeln('');
            return;
        }

        foreach ($inspection->parameters as $parameter) {
            $output->writeln('  ' . $this->formatParameter($parameter));
        }

        $output->writeln('');
        $output->writeln(sprintf('<fg=cyan>Resolvable:</>   %d', $inspection->resolvableCount()));
        $output->writeln(sprintf('<fg=cyan>Unresolvable:</> %d', $inspection->unresolvableCount()));
        $output->writeln('');

        if (!$inspection->isFullyResolvable()) {
            $output->writeln('<comment>Unresolvable parameters need an explicit binding or default value.</comment>');
            $output->writeln('');
        }
    }

    private function formatParameter(ParameterInspection $parameter): string
    {
        $marker = $parameter->isResolvable() ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $detail = $parameter->isResolvable()
            ? $this->parenthesize($parameter)
            : sprintf('<fg=red>%s</>', $parameter->detail);

        return sprintf('%s %s %s %s', $marker, $parameter->name, $parameter->renderedType, $detail);
    }

    private function parenthesize(ParameterInspection $parameter): string
    {
        if ($parameter->status === ParameterStatus::HasDefault) {
            return $parameter->detail;
        }

        return '(' . $parameter->detail . ')';
    }

    private function resolveClassName(string $argument, OutputInterface $output): ?string
    {
        if (!is_file($argument)) {
            return ltrim($argument, '\\');
        }

        $contents = (string) file_get_contents($argument);
        $fqcn = $this->extractFqcnFromSource($contents);

        if ($fqcn === null) {
            $output->writeln(sprintf(
                '<error>File "%s" does not declare a class, interface, trait, or enum</error>',
                $argument,
            ));
            return null;
        }

        require_once $argument;

        return $fqcn;
    }

    private function extractFqcnFromSource(string $source): ?string
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespaceName($tokens, $i);
                continue;
            }

            if ($this->isClassLikeDeclaration($token[0]) && !$this->isAnonymousClass($tokens, $i)) {
                $name = $this->readNextIdentifier($tokens, $i);
                if ($name === null) {
                    continue;
                }

                return $namespace === '' ? $name : $namespace . '\\' . $name;
            }
        }

        return null;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readNamespaceName(array $tokens, int $from): string
    {
        $name = '';
        $count = count($tokens);

        for ($i = $from + 1; $i < $count; ++$i) {
            $token = $tokens[$i];

            if ($token === ';' || $token === '{') {
                break;
            }

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR || $token[0] === T_NAME_QUALIFIED) {
                $name .= $token[1];
            }
        }

        return trim($name, '\\');
    }

    private function isClassLikeDeclaration(int $tokenType): bool
    {
        if ($tokenType === T_CLASS || $tokenType === T_INTERFACE || $tokenType === T_TRAIT) {
            return true;
        }

        return defined('T_ENUM') && $tokenType === T_ENUM;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isAnonymousClass(array $tokens, int $index): bool
    {
        for ($j = $index - 1; $j >= 0; --$j) {
            $previous = $tokens[$j];

            if (is_array($previous) && $previous[0] === T_WHITESPACE) {
                continue;
            }

            return is_array($previous) && $previous[0] === T_NEW;
        }

        return false;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readNextIdentifier(array $tokens, int $from): ?string
    {
        $count = count($tokens);

        for ($i = $from + 1; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }

        return null;
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
Inspect the constructor signature of a class and report whether each parameter
can be resolved through the Gacela container.

Accepts either a fully qualified class name or a path to a PHP file that
declares the target class.

<info>Resolution categories:</info>
  <fg=green>✓ bound</fg=green>        a binding in gacela.php maps the type to a concrete implementation
  <fg=green>✓ autowirable</fg=green>  concrete class exists and will be constructed automatically
  <fg=green>✓ default</fg=green>      the parameter has a default value
  <fg=green>✓ inject</fg=green>       parameter is annotated with #[Inject] (optionally with an implementation override)
  <fg=red>✗ scalar</fg=red>       built-in type (string, int, ...) with no default
  <fg=red>✗ interface</fg=red>    interface type with no binding
  <fg=red>✗ missing</fg=red>      type does not exist

<info>Examples:</info>
  bin/gacela debug:dependencies "App\MyModule\MyFactory"
  bin/gacela debug:dependencies src/MyModule/MyFactory.php
HELP;
    }
}
