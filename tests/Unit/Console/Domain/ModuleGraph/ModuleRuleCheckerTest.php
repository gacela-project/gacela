<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph;

use Gacela\Console\Domain\ModuleGraph\ModuleRuleChecker;
use Gacela\Console\Domain\ModuleGraph\ModuleRuleCheckResult;
use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;
use PHPUnit\Framework\TestCase;

final class ModuleRuleCheckerTest extends TestCase
{
    public function test_a_graph_without_rules_is_clean(): void
    {
        $result = $this->check(
            ['App\Payment' => ['App\Admin']],
            ModuleRuleSet::empty(),
        );

        self::assertTrue($result->isClean());
    }

    public function test_a_denied_edge_present_in_the_graph_is_reported(): void
    {
        $result = $this->check(
            ['App\Payment' => ['App\Admin'], 'App\Admin' => []],
            $this->rules([['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'reviewed']]),
        );

        self::assertFalse($result->isClean());
        self::assertCount(1, $result->violations);
        self::assertSame('App\Payment', $result->violations[0]->from);
        self::assertSame('App\Admin', $result->violations[0]->to);
        self::assertSame('reviewed', $result->violations[0]->reason);
    }

    public function test_a_denied_edge_absent_from_the_graph_is_not_reported(): void
    {
        $result = $this->check(
            ['App\Payment' => [], 'App\Admin' => []],
            $this->rules([['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'reviewed']]),
        );

        self::assertTrue($result->isClean());
        self::assertSame([], $result->violations);
    }

    public function test_every_forbidden_edge_is_reported_not_only_the_first(): void
    {
        $result = $this->check(
            [
                'App\Payment' => ['App\Admin', 'App\Legacy'],
                'App\Admin' => [],
                'App\Legacy' => [],
            ],
            $this->rules([[
                'from' => 'App\Payment',
                'deny' => ['App\Admin', 'App\Legacy'],
                'reason' => 'reviewed',
            ]]),
        );

        self::assertCount(2, $result->violations);
    }

    /**
     * The self-invalidating half: a rule about a module nobody has any more
     * reads as a boundary still being watched.
     */
    public function test_a_rule_naming_a_module_that_does_not_exist_is_reported(): void
    {
        $result = $this->check(
            ['App\Payment' => [], 'App\Admin' => []],
            $this->rules([['from' => 'App\Payment', 'deny' => ['App\Gone'], 'reason' => 'reviewed']]),
        );

        self::assertFalse($result->isClean());
        self::assertSame(['App\Gone'], $result->unknownNamespaces);
        self::assertSame([], $result->violations);
    }

    public function test_a_namespace_covering_only_submodules_is_known(): void
    {
        $result = $this->check(
            ['App\Payment\Refunds' => [], 'App\Admin' => []],
            $this->rules([['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'reviewed']]),
        );

        self::assertSame([], $result->unknownNamespaces);
    }

    public function test_a_namespace_is_unknown_when_only_a_longer_module_name_starts_with_its_letters(): void
    {
        $result = $this->check(
            ['App\Payments' => [], 'App\Admin' => []],
            $this->rules([['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'reviewed']]),
        );

        self::assertSame(['App\Payment'], $result->unknownNamespaces);
    }

    public function test_an_allow_list_reports_the_dependencies_it_does_not_permit(): void
    {
        $result = $this->check(
            [
                'App\Reporting' => ['App\Shared', 'App\Payment'],
                'App\Shared' => [],
                'App\Payment' => [],
            ],
            $this->rules([[
                'from' => 'App\Reporting',
                'allow' => ['App\Shared'],
                'reason' => 'read-only module',
            ]]),
        );

        self::assertCount(1, $result->violations);
        self::assertSame('App\Payment', $result->violations[0]->to);
    }

    /**
     * @param array<string, list<string>> $graph
     */
    private function check(array $graph, ModuleRuleSet $rules): ModuleRuleCheckResult
    {
        return (new ModuleRuleChecker())->check($graph, $rules);
    }

    /**
     * @param list<mixed> $entries
     */
    private function rules(array $entries): ModuleRuleSet
    {
        return ModuleRuleSet::fromDecodedJson(['rules' => $entries]);
    }
}
