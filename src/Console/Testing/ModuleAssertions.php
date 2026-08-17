<?php

declare(strict_types=1);

namespace Gacela\Console\Testing;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Console\Domain\ModuleGraph\CycleAllowList;
use Gacela\Console\Domain\ModuleGraph\ModuleGraphBuilder;
use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;
use Gacela\StaticAnalysis\NamespaceMatch;
use JsonException;
use PHPUnit\Framework\Assert;
use ReflectionClass;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function class_exists;
use function count;
use function file_get_contents;
use function implode;
use function in_array;
use function interface_exists;
use function is_array;
use function is_file;
use function json_decode;
use function ltrim;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * The module boundary assertions, for a PHPUnit test of any base class.
 *
 * ```php
 * self::assertModuleDependsOnlyOn(InvoiceFacade::class, [BillingFacade::class]);
 * self::assertNoModuleCycles(__DIR__ . '/allowed-cycles.json');
 * self::assertModuleRulesHold(__DIR__ . '/module-rules.json');
 * ```
 *
 * Every answer here comes from the graph `debug:graph --check` reads, through
 * the same {@see ModuleGraphBuilder}, cycle detector, allow list and rule
 * checker. That is the whole point: a boundary decision that held in CI and not
 * in the test suite -- or the other way round -- is a decision nobody can act
 * on, so the two are the same code rather than two readings of one idea.
 *
 * **Why this lives in the Console namespace.** The module graph is built by
 * scanning source files, which is console work, and `Gacela\Framework` does not
 * reference `Gacela\Console` in a single file. A framework that sells module
 * boundaries inverting its own would be a poor advertisement, so the slice
 * helper -- {@see \Gacela\Framework\Testing\GacelaTestCase::bootstrapModule()},
 * which needs only framework primitives -- stayed there and this came here. A
 * project with its own base test class writes `use ModuleAssertions;` and has
 * both.
 *
 * The application must be bootstrapped: these read the modules the running
 * configuration declares, which is what makes a filtered `setAppModulePaths()`
 * mean the same thing here as it does on the command line.
 */
trait ModuleAssertions
{
    /**
     * Assert that a module's dependencies are all covered by the given list.
     *
     * A module is named by any class inside it -- its Facade, by convention --
     * or by its namespace. An allowance may name a namespace that holds several
     * modules, which is how a decision about a whole area of an application is
     * written; naming a module the running configuration does not scan fails
     * rather than passing on an empty dependency list.
     *
     * @param list<string> $allowedModules facade classes or module namespaces
     */
    protected static function assertModuleDependsOnlyOn(string $module, array $allowedModules): void
    {
        $modules = self::gacelaAppModules();
        $graph = (new ModuleGraphBuilder())->build($modules);
        $namespace = self::gacelaNamespaceOf($module);

        if (!isset($graph[$namespace])) {
            Assert::fail(self::gacelaUnknownModuleReport($module, $namespace, array_keys($graph)));
        }

        $allowed = array_map(self::gacelaNamespaceOf(...), $allowedModules);

        $forbidden = array_values(array_filter(
            $graph[$namespace],
            static fn (string $dependency): bool => !NamespaceMatch::anyCovers($allowed, $dependency),
        ));

        Assert::assertSame(
            [],
            array_map(static fn (string $dependency): string => $namespace . ' -> ' . $dependency, $forbidden),
            self::gacelaForbiddenDependencyReport($modules, $namespace, $forbidden, $allowed),
        );
    }

    /**
     * Assert that the module graph holds no dependency cycle nobody reviewed.
     *
     * The optional file has the shape `debug:graph --check --allowed-cycles`
     * reads -- a list of entries, each with a `modules` list and a `reason` --
     * and is as self-invalidating here as it is there: an allowance whose cycle
     * has since been broken fails, because an allow list that outlives what it
     * allows is only a way to keep the check quiet.
     */
    protected static function assertNoModuleCycles(string $allowedCyclesFile = ''): void
    {
        $modules = self::gacelaAppModules();
        $graph = (new ModuleGraphBuilder())->build($modules);

        $cycles = self::gacelaConsole()->detectModuleCycles($graph);
        $result = self::gacelaCycleAllowList($allowedCyclesFile)->check($cycles);

        $findings = [];
        foreach ($result->undeclaredCycles as $cycle) {
            $findings[] = 'Dependency cycle: ' . implode(' -> ', $cycle);
        }

        foreach ($result->staleAllowances as $cycle) {
            $findings[] = 'Allowed cycle no longer exists: ' . implode(' -> ', $cycle);
        }

        Assert::assertSame([], $findings, self::gacelaCycleReport($modules, $graph, $result->undeclaredCycles, $result->staleAllowances));
    }

    /**
     * Assert that the declared module rules hold against the graph the
     * application actually has.
     *
     * The file is the one `debug:graph --check --rules` reads, and the one the
     * PHPStan and Psalm rules read: a project keeps a single `module-rules.json`
     * and enforces it from wherever it is looking.
     */
    protected static function assertModuleRulesHold(string $rulesFile): void
    {
        $modules = self::gacelaAppModules();
        $graph = (new ModuleGraphBuilder())->build($modules);

        $result = self::gacelaConsole()->checkModuleRules($graph, ModuleRuleSet::fromFile($rulesFile));

        $findings = [];
        foreach ($result->violations as $violation) {
            $findings[] = sprintf('Forbidden dependency: %s -> %s', $violation->from, $violation->to);
        }

        foreach ($result->unknownNamespaces as $namespace) {
            $findings[] = sprintf('Module rule governs nothing: %s', $namespace);
        }

        Assert::assertSame([], $findings, self::gacelaRuleReport($modules, $result->violations, $result->unknownNamespaces));
    }

    /**
     * The modules the running configuration declares, exactly as the console
     * finds them.
     *
     * @return list<AppModule>
     */
    private static function gacelaAppModules(): array
    {
        return self::gacelaConsole()->findAllAppModules();
    }

    private static function gacelaConsole(): ConsoleFacade
    {
        return new ConsoleFacade();
    }

    /**
     * The module a caller named: the namespace of the class, when it is one,
     * and otherwise the string itself.
     *
     * Any class inside a module names it, so a Factory or a domain service in
     * the module's own namespace works as well as the Facade. A class in a
     * *sub*namespace does not, and that mistake is caught by the module not
     * being in the graph rather than by guessing which ancestor was meant.
     */
    private static function gacelaNamespaceOf(string $module): string
    {
        $name = ltrim($module, '\\');

        return class_exists($name) || interface_exists($name)
            ? (new ReflectionClass($name))->getNamespaceName()
            : $name;
    }

    /**
     * @param list<string> $knownModules
     */
    private static function gacelaUnknownModuleReport(string $module, string $namespace, array $knownModules): string
    {
        return sprintf(
            "\"%s\" is not a module of the bootstrapped application: no module has the namespace \"%s\".\n"
            . "Name a module by its Facade class or by its namespace, and check the bootstrap's setAppModulePaths().\n"
            . "The scan found:\n%s",
            $module,
            $namespace,
            self::gacelaBulletList($knownModules),
        );
    }

    /**
     * @param list<AppModule> $modules
     * @param list<string>    $forbidden
     * @param list<string>    $allowed
     */
    private static function gacelaForbiddenDependencyReport(array $modules, string $namespace, array $forbidden, array $allowed): string
    {
        $report = sprintf(
            "\"%s\" may depend only on:\n%s\n",
            $namespace,
            $allowed === [] ? '  (nothing)' : self::gacelaBulletList($allowed),
        );

        foreach ($forbidden as $dependency) {
            $report .= sprintf("\n✗ %s -> %s\n", $namespace, $dependency);
            $report .= self::gacelaImportsBehind($modules, $namespace, $dependency);
        }

        return $report;
    }

    /**
     * @param list<AppModule>             $modules
     * @param array<string, list<string>> $graph
     * @param list<list<string>>          $undeclared
     * @param list<list<string>>          $stale
     */
    private static function gacelaCycleReport(array $modules, array $graph, array $undeclared, array $stale): string
    {
        $report = sprintf(
            "%d undeclared module dependency cycle(s), %d allowance(s) that no longer apply.\n",
            count($undeclared),
            count($stale),
        );

        foreach ($undeclared as $cycle) {
            $report .= sprintf("\n✗ Dependency cycle: %s\n", implode(' -> ', $cycle));

            foreach ($cycle as $from) {
                foreach ($graph[$from] ?? [] as $to) {
                    if (in_array($to, $cycle, true)) {
                        $report .= self::gacelaImportsBehind($modules, $from, $to);
                    }
                }
            }
        }

        foreach ($stale as $cycle) {
            $report .= sprintf(
                "\n✗ Allowed cycle no longer exists: %s. Remove it from the allow list.\n",
                implode(' -> ', $cycle),
            );
        }

        return $report;
    }

    /**
     * @param list<AppModule>           $modules
     * @param list<ModuleRuleViolation> $violations
     * @param list<string>              $unknownNamespaces
     */
    private static function gacelaRuleReport(array $modules, array $violations, array $unknownNamespaces): string
    {
        $report = sprintf(
            "%d forbidden dependency(ies), %d rule(s) governing nothing.\n",
            count($violations),
            count($unknownNamespaces),
        );

        foreach ($violations as $violation) {
            $report .= sprintf("\n✗ Forbidden dependency: %s -> %s (%s)\n", $violation->from, $violation->to, $violation->reason);
            $report .= self::gacelaImportsBehind($modules, $violation->from, $violation->to);
        }

        foreach ($unknownNamespaces as $namespace) {
            $report .= sprintf(
                "\n✗ Module rule governs nothing: %s matches no module. Remove the rule, or fix the namespace.\n",
                $namespace,
            );
        }

        return $report;
    }

    /**
     * The `use` statements that write one edge, each as `file:line`.
     *
     * The line is where the statement opens; a grouped import reaching two
     * modules is one statement and reports one line for both. What is *not*
     * here is a dependency that arrives without an import -- a fully qualified
     * name written inline, or a class-string in configuration -- because the
     * graph does not see those either, so nothing can be reported that the
     * check did not find.
     *
     * @param list<AppModule> $modules
     */
    private static function gacelaImportsBehind(array $modules, string $from, string $to): string
    {
        $module = self::gacelaModuleNamed($modules, $from);
        if (!$module instanceof AppModule) {
            return '';
        }

        $lines = '';
        foreach ((new ModuleGraphBuilder())->importsPointingInto($module, $to) as $import) {
            $lines .= sprintf("    %s:%d  use %s;\n", $import['file'], $import['line'], $import['import']);
        }

        return $lines;
    }

    /**
     * @param list<AppModule> $modules
     */
    private static function gacelaModuleNamed(array $modules, string $namespace): ?AppModule
    {
        foreach ($modules as $module) {
            if ($module->fullModuleName() === $namespace) {
                return $module;
            }
        }

        return null;
    }

    private static function gacelaCycleAllowList(string $path): CycleAllowList
    {
        if ($path === '') {
            return CycleAllowList::empty();
        }

        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw ModuleAssertionException::unreadableFile($path, 'allowed cycles');
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw ModuleAssertionException::invalidJson($path, 'allowed cycles', $jsonException->getMessage());
        }

        if (!is_array($decoded)) {
            throw ModuleAssertionException::notAListOfEntries($path, 'allowed cycles');
        }

        return CycleAllowList::fromDecodedJson($decoded);
    }

    /**
     * @param list<string> $items
     */
    private static function gacelaBulletList(array $items): string
    {
        return implode("\n", array_map(static fn (string $item): string => '  - ' . $item, $items));
    }
}
