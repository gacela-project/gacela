<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Debug\ConstructorInspection;
use Gacela\Console\Application\Debug\ConstructorInspector;
use Gacela\Console\Application\Debug\DependencyTreeInspection;
use Gacela\Console\Application\Debug\DependencyTreeInspector;
use Gacela\Console\Application\Debug\DependencyTreeNode;
use Gacela\Console\Application\Debug\DependencyTreeRenderer;
use Gacela\Console\Application\Debug\ParameterInspection;
use Gacela\Console\Application\Debug\ParameterStatus;
use PhpToken;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;
use function class_exists;
use function count;
use function interface_exists;
use function json_encode;
use function ltrim;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class DebugDependenciesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('debug:dependencies')
            ->setDescription('Show the constructor parameters of a class and their resolvability through the container')
            ->setHelp($this->getHelpText())
            ->addArgument('class', InputArgument::REQUIRED, 'Fully qualified class name or a path to a PHP file declaring the class')
            ->addOption('tree', null, InputOption::VALUE_NONE, 'Also show the transitive dependency tree as the container resolves it')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Report as a JSON document instead of text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $argument */
        $argument = $input->getArgument('class');
        $withTree = $input->getOption('tree') === true;
        $asJson = ConsoleInput::format($input) === 'json';

        $className = $this->resolveClassName($argument, $output, $asJson);
        if ($className === null) {
            return Command::FAILURE;
        }

        // Inline rather than inside the helper below: this pair is what narrows
        // `string` to `class-string` for the inspectors, and the project's
        // static-analysis rules forbid doing that with an inline `@var`.
        if (!class_exists($className) && !interface_exists($className)) {
            $this->writeError($output, sprintf('Class "%s" does not exist', $className), $asJson);

            return Command::FAILURE;
        }

        $refusal = $this->refuseNonConcrete($className);
        if ($refusal !== null) {
            $this->writeError($output, $refusal, $asJson);

            return Command::FAILURE;
        }

        $inspection = (new ConstructorInspector())->inspect($className);

        if ($asJson) {
            $output->writeln($this->encode($this->asDocument(
                $inspection,
                $withTree ? (new DependencyTreeInspector())->inspect($className) : null,
            )));

            return Command::SUCCESS;
        }

        $this->renderInspection($output, $inspection);

        if ($withTree) {
            $this->renderTree($output, (new DependencyTreeInspector())->inspect($className));
        }

        return Command::SUCCESS;
    }

    /**
     * Why an existing class-like cannot be inspected, or null when it can.
     *
     * @param class-string $className
     */
    private function refuseNonConcrete(string $className): ?string
    {
        $reflection = new ReflectionClass($className);

        if ($reflection->isInterface()) {
            return sprintf('"%s" is an interface — pass a concrete class instead', $className);
        }

        if ($reflection->isAbstract()) {
            return sprintf('"%s" is abstract — pass a concrete class instead', $className);
        }

        return null;
    }

    /**
     * One message, said in whichever shape the run asked for -- so a consumer
     * piping this into a parser gets a document on the runs that refused too.
     */
    private function writeError(OutputInterface $output, string $message, bool $asJson): void
    {
        $output->writeln($asJson ? $this->encode(['error' => $message]) : sprintf('<error>%s</error>', $message));
    }

    /**
     * The same report as a document.
     *
     * `parameters` is the shape `debug:modules --json` uses for a pillar and
     * `tree` the one `debug:container --json` uses for a class, so the three
     * commands describe one parameter and one tree the same way rather than
     * inventing a vocabulary each.
     *
     * `tree` is present only with `--tree`, exactly as the text report only
     * grows that section with the flag: it means the same thing in both
     * formats, and building a transitive graph nobody asked for is not free.
     *
     * @return array<string, mixed>
     */
    private function asDocument(ConstructorInspection $inspection, ?DependencyTreeInspection $tree): array
    {
        $document = [
            'class' => $inspection->className,
            'hasConstructor' => $inspection->hasConstructor,
            'resolvable' => $inspection->resolvableCount(),
            'unresolvable' => $inspection->unresolvableCount(),
            'parameters' => array_map(
                static fn (ParameterInspection $parameter): array => [
                    // Without the `$` the text prints, matching
                    // `debug:modules --json`: a document is matched against a
                    // reflection parameter rather than read.
                    'name' => ltrim($parameter->name, '$'),
                    'type' => $parameter->renderedType,
                    'status' => $parameter->status->value,
                    'detail' => $parameter->detail,
                    'resolvable' => $parameter->isResolvable(),
                ],
                $inspection->parameters,
            ),
        ];

        if ($tree instanceof DependencyTreeInspection) {
            $document['containerAvailable'] = $tree->containerAvailable;
            $document['total'] = count($tree->nodes);
            $document['tree'] = array_map($this->treeNode(...), $tree->tree);
        }

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function treeNode(DependencyTreeNode $node): array
    {
        return [
            'class' => $node->className,
            'parameter' => $node->parameter,
            'status' => $node->status->value,
            'repeated' => $node->repeated,
            'children' => array_map($this->treeNode(...), $node->children),
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function encode(array $document): string
    {
        return json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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

        foreach ((new DependencyTreeRenderer())->render($inspection->tree, '  ') as $line) {
            $output->writeln($line);
        }

        $output->writeln('');
        // Counted off the flat list, not the branches: a class three parents
        // pull in is one dependency drawn three times.
        $output->writeln(sprintf('<fg=cyan>Dependencies:</> %d', count($inspection->nodes)));
        $output->writeln('');
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

    private function resolveClassName(string $argument, OutputInterface $output, bool $asJson): ?string
    {
        if (!is_file($argument)) {
            return ltrim($argument, '\\');
        }

        $contents = (string) file_get_contents($argument);
        $fqcn = $this->extractFqcnFromSource($contents);

        if ($fqcn === null) {
            $this->writeError(
                $output,
                sprintf('File "%s" does not declare a class, interface, trait, or enum', $argument),
                $asJson,
            );

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

Nodes are drawn where they sit, labelled with the constructor parameter that
pulled them in, so a missing dependency four levels down says whose it is. A
constructor cycle is marked <fg=yellow>(cycle)</fg=yellow> and cut rather than
followed. The <info>Dependencies</info> count is of distinct classes, so one
pulled in by three parents counts once and is drawn three times.

<info>Examples:</info>
  bin/gacela debug:dependencies "App\MyModule\MyFactory"
  bin/gacela debug:dependencies src/MyModule/MyFactory.php
  bin/gacela debug:dependencies "App\MyModule\MyFactory" --tree
HELP;
    }
}
