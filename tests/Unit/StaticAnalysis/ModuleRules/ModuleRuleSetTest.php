<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\ModuleRules;

use Gacela\StaticAnalysis\ModuleRules\MalformedModuleRulesException;
use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;
use PHPUnit\Framework\TestCase;

final class ModuleRuleSetTest extends TestCase
{
    public function test_an_empty_rule_set_forbids_nothing(): void
    {
        $rules = ModuleRuleSet::empty();

        self::assertTrue($rules->isEmpty());
        self::assertNull($rules->violationFor('App\Payment', 'App\Admin'));
    }

    public function test_a_denied_edge_is_a_violation_carrying_its_reason(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'billing must not reach back-office'],
        ]);

        $violation = $rules->violationFor('App\Payment', 'App\Admin');

        self::assertNotNull($violation);
        self::assertSame('App\Payment', $violation->from);
        self::assertSame('App\Admin', $violation->to);
        self::assertSame('billing must not reach back-office', $violation->reason);
    }

    public function test_an_edge_no_rule_denies_is_allowed(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'reviewed'],
        ]);

        self::assertNull($rules->violationFor('App\Payment', 'App\Shipping'));
    }

    public function test_a_rule_only_judges_edges_leaving_its_own_module(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'reviewed'],
        ]);

        self::assertNull($rules->violationFor('App\Shipping', 'App\Admin'));
    }

    public function test_an_allow_list_turns_every_unlisted_edge_into_a_violation(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Reporting', 'allow' => ['App\Shared'], 'reason' => 'read-only module'],
        ]);

        self::assertNull($rules->violationFor('App\Reporting', 'App\Shared'));

        $violation = $rules->violationFor('App\Reporting', 'App\Payment');
        self::assertNotNull($violation);
        self::assertSame('read-only module', $violation->reason);
    }

    /**
     * A leaf module: legitimate, and the one case where an empty list is not
     * the same as an absent key.
     */
    public function test_an_empty_allow_list_forbids_every_dependency(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Reporting', 'allow' => [], 'reason' => 'leaf module, depends on nothing'],
        ]);

        self::assertNotNull($rules->violationFor('App\Reporting', 'App\Shared'));
    }

    public function test_a_submodule_of_the_declaring_module_is_reachable_under_an_allow_list(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Reporting', 'allow' => ['App\Shared'], 'reason' => 'read-only module'],
        ]);

        self::assertNull($rules->violationFor('App\Reporting', 'App\Reporting\Export'));
    }

    public function test_a_rule_applies_to_the_submodules_of_the_module_it_names(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'reviewed'],
        ]);

        self::assertNotNull($rules->violationFor('App\Payment\Refunds', 'App\Admin\Users'));
    }

    /**
     * Prefix matching that ignores the namespace boundary would let `App\Pay`
     * silently govern `App\Payment`.
     */
    public function test_matching_respects_the_namespace_boundary(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Pay', 'deny' => ['App\Adm'], 'reason' => 'reviewed'],
        ]);

        self::assertNull($rules->violationFor('App\Payment', 'App\Admin'));
    }

    public function test_matching_is_case_sensitive(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'reviewed'],
        ]);

        self::assertNull($rules->violationFor('app\payment', 'app\admin'));
    }

    public function test_every_namespace_a_rule_names_is_declared(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Payment', 'deny' => ['App\Admin', 'App\Legacy'], 'reason' => 'reviewed'],
            ['from' => 'App\Reporting', 'allow' => ['App\Shared'], 'reason' => 'read-only'],
        ]);

        self::assertSame(
            ['App\Payment', 'App\Admin', 'App\Legacy', 'App\Reporting', 'App\Shared'],
            $rules->declaredNamespaces(),
        );
    }

    public function test_the_first_matching_rule_decides(): void
    {
        $rules = $this->rules([
            ['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'first'],
            ['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'second'],
        ]);

        $violation = $rules->violationFor('App\Payment', 'App\Admin');

        self::assertNotNull($violation);
        self::assertSame('first', $violation->reason);
    }

    public function test_a_missing_rules_key_is_malformed(): void
    {
        $this->expectException(MalformedModuleRulesException::class);

        ModuleRuleSet::fromDecodedJson(['whatever' => []]);
    }

    public function test_an_entry_that_is_not_an_object_is_malformed(): void
    {
        $this->expectException(MalformedModuleRulesException::class);

        ModuleRuleSet::fromDecodedJson(['rules' => ['App\Payment']]);
    }

    public function test_an_entry_without_a_from_is_malformed(): void
    {
        $this->expectException(MalformedModuleRulesException::class);

        $this->rules([['deny' => ['App\Admin'], 'reason' => 'reviewed']]);
    }

    public function test_an_entry_with_neither_allow_nor_deny_is_malformed(): void
    {
        $this->expectException(MalformedModuleRulesException::class);

        $this->rules([['from' => 'App\Payment', 'reason' => 'reviewed']]);
    }

    public function test_an_entry_with_both_allow_and_deny_is_malformed(): void
    {
        $this->expectException(MalformedModuleRulesException::class);

        $this->rules([[
            'from' => 'App\Payment',
            'allow' => ['App\Shared'],
            'deny' => ['App\Admin'],
            'reason' => 'reviewed',
        ]]);
    }

    public function test_an_empty_deny_list_is_malformed(): void
    {
        $this->expectException(MalformedModuleRulesException::class);

        $this->rules([['from' => 'App\Payment', 'deny' => [], 'reason' => 'reviewed']]);
    }

    public function test_an_entry_without_a_reason_is_malformed(): void
    {
        $this->expectException(MalformedModuleRulesException::class);

        $this->rules([['from' => 'App\Payment', 'deny' => ['App\Admin']]]);
    }

    public function test_the_message_names_the_offending_entry(): void
    {
        $this->expectExceptionMessageMatches('/#1/');

        $this->rules([
            ['from' => 'App\Payment', 'deny' => ['App\Admin'], 'reason' => 'reviewed'],
            ['from' => 'App\Reporting', 'deny' => ['App\Admin']],
        ]);
    }

    /**
     * @param list<mixed> $entries
     */
    private function rules(array $entries): ModuleRuleSet
    {
        return ModuleRuleSet::fromDecodedJson(['rules' => $entries]);
    }
}
