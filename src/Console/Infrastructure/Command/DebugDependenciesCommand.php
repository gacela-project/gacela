<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Debug\ConstructorInspection;
use Gacela\Console\Application\Debug\ConstructorInspector;
use Gacela\Console\Application\Debug\DependencyNode;
use Gacela\Console\Application\Debug\DependencyTreeInspection;
use Gacela\Console\Application\Debug\DependencyTreeInspector;
use Gacela\Console\Application\Debug\ParameterInspection;
use Gacela\Console\Application\Debug\ParameterStatus;
use PhpToken;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function class_exists;
use function count;
use function interface_exists;
use function ltrim;
use function sprintf;

final class DebugDependenciesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('debug:dependencies')
            ->setDescription('Show the constructor parameters of a class and their resolvability through the container')
            ->setHelp($this->getHelpText())
            ->addArgument('class', InputArgument::REQUIRED, 'Fully qualified class name or a path to a PHP file declaring the class')
            ->addOption('tree', null, InputOption::VALUE_NONE, 'Also show the transitive dependency tree as the container resolves it');
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

        $this->renderInspection($output, (new ConstructorInspector())->inspect($className));

        if ($input->getOption('tree') === true) {
            $this->renderTree($output, (new DependencyTreeInspector())->inspect($className));
        }

        return Command::SUCCESS;
    }

    private function renderTree(OutputInterface $output, DependencyTreeInspection $inspection): void
    {
        ConsoleSection::title($output, sprintf('Dependency tree for %s', $inspection->className));

        if (!$inspection->containerAvailable) {
            $output->writeln('  <comment>No container available — bootstrap Gacela to resolve the tree.</comment>');
            $output->writeln('');
            return;
        }

        if ($inspection->nodes === []) {
            $output->writeln('  <fg=cyan>No transitive dependencies</>');
            $output->writeln('');
            return;
        }

        foreach ($inspection->nodes as $node) {
            $output->writeln('  ' . $this->formatNode($node));
        }

        $output->writeln('');
        $output->writeln(sprintf('<fg=cyan>Dependencies:</> %d', count($inspection->nodes)));
        $output->writeln('');
    }

    private function formatNode(DependencyNode $node): string
    {
        $marker = $node->isProvided() ? '<fg=green>✓</>' : '<fg=red>✗</>';

        return sprintf('%s %s (%s)', $marker, $node->className, $node->status->value);
    }

    private function renderInspection(OutputInterface $output, ConstructorInspection $inspection): void
    {
        ConsoleSection::title($output, sprintf('Constructor dependencies for %s', $inspection->className));

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

    /**
     * Reports the first named class-like declaration in the source.
     *
     * The PHP 8 tokenizer emits a namespace and a declared name as one token
     * each, so a single pass over the significant tokens is enough: nothing to
     * accumulate, nothing to backtrack over.
     */
    private function extractFqcnFromSource(string $source): ?string
    {
        $tokens = $this->significantTokens($source);
        $namespace = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if ($token->is(T_NAMESPACE)) {
                $namespace = $this->textAfter($tokens, $i, [T_STRING, T_NAME_QUALIFIED]) ?? '';
                continue;
            }

            if (!$token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                continue;
            }

            // Only a declaration is followed by an identifier: `new class {}`
            // and `Foo::class` are followed by punctuation or a keyword.
            $name = $this->textAfter($tokens, $i, [T_STRING]);
            if ($name === null) {
                continue;
            }

            return $namespace === '' ? $name : $namespace . '\\' . $name;
        }

        return null;
    }

    /**
     * @return list<PhpToken>
     */
    private function significantTokens(string $source): array
    {
        return array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => !$token->isIgnorable(),
        ));
    }

    /**
     * @param list<PhpToken> $tokens
     * @param list<int> $types
     */
    private function textAfter(array $tokens, int $index, array $types): ?string
    {
        $next = $tokens[$index + 1] ?? null;

        if ($next === null || !$next->is($types)) {
            return null;
        }

        return $next->text;
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

<info>--tree</info> appends the transitive dependency tree, taken from the container
rather than re-derived from type hints, so bindings and contextual bindings are
already applied. Each node reports how the container will supply it:

  <fg=green>✓ binding</fg=green>      an explicit binding is registered for the id
  <fg=green>✓ instance</fg=green>     the container already holds an instance or resolved singleton
  <fg=green>✓ autowired</fg=green>    nothing registered, but the class will be constructed on demand
  <fg=red>✗ unresolvable</fg=red> the container owns nothing and the class cannot be built

<info>Examples:</info>
  bin/gacela debug:dependencies "App\MyModule\MyFactory"
  bin/gacela debug:dependencies src/MyModule/MyFactory.php
  bin/gacela debug:dependencies "App\MyModule\MyFactory" --tree
HELP;
    }
}
