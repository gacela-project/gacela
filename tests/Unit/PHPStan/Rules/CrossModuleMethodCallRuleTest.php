<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\PHPStan\Rules\CrossModuleMethodCallRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use function sprintf;

/**
 * @extends RuleTestCase<CrossModuleMethodCallRule>
 */
final class CrossModuleMethodCallRuleTest extends RuleTestCase
{
    private const ROOT = 'GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule';

    /** @var list<string> */
    private array $sharedNamespaces = [];

    /** @var list<string> */
    private array $ignoreReceivers = [];

    private ?Rule $rule = null;

    /**
     * The headline case. `ShopService` is written once, in a constructor
     * type-hint; the call site names nothing, so the name-matching rule sees
     * no crossing at all.
     */
    public function test_a_call_on_an_injected_dependency_from_another_module_is_reported(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/UserCalls/InjectedFactory.php'],
            [[$this->expectedError('InjectedFactory', 'Shop\Domain\ShopService', 'Shop'), 22, $this->expectedTip('Shop')]],
        );
    }

    /**
     * `?->` crosses the boundary exactly as much as `->`. The rule is a
     * `Rule<MethodCall>` and `NullsafeMethodCall` is a sibling node, not a
     * subclass, so this is pinned rather than assumed: PHPStan reaches it, and
     * the Psalm half handles the node explicitly.
     */
    public function test_a_nullsafe_call_on_an_injected_dependency_is_reported(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/UserCalls/NullsafeCallFactory.php'],
            [[$this->expectedError('NullsafeCallFactory', 'Shop\Domain\ShopService', 'Shop'), 21, $this->expectedTip('Shop')]],
        );
    }

    public function test_a_call_on_a_facade_is_allowed(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/UserCalls/ViaFacadeFactory.php'], []);
    }

    /**
     * Consumers hold the interface rather than the Facade, which is the same
     * sanctioned crossing.
     */
    public function test_a_call_on_a_facade_interface_is_allowed(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/UserCalls/ViaFacadeInterfaceFactory.php'], []);
    }

    public function test_a_call_inside_the_same_module_is_allowed(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/UserCalls/SameModuleCallFactory.php'], []);
    }

    public function test_a_call_on_a_shared_kernel_type_is_allowed_when_allowlisted(): void
    {
        $this->sharedNamespaces = [self::ROOT . '\Shared'];

        $this->analyse([__DIR__ . '/Fixture/CrossModule/UserCalls/SharedCallFactory.php'], []);
    }

    /**
     * The same call without the allowance: the exemption is what silences it,
     * not the namespace being called `Shared`.
     */
    public function test_a_call_on_a_shared_kernel_type_is_reported_when_not_allowlisted(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/UserCalls/SharedCallFactory.php'],
            [[$this->expectedError('SharedCallFactory', 'Shared\Clock', 'Shared'), 18, $this->expectedTip('Shared')]],
        );
    }

    /**
     * An unresolvable receiver is not evidence of a violation, and guessing
     * would turn the rule into noise on every `mixed` left in a codebase.
     */
    public function test_a_call_on_an_untyped_receiver_is_not_reported(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/UserCalls/UntypedReceiverFactory.php'], []);
    }

    /**
     * A module throws its own exception type and a neighbour catches it and
     * asks for `getMessage()`. Reading, not collaborating -- and reported, this
     * made every `catch` of a typed exception a finding.
     */
    public function test_a_call_on_a_caught_exception_from_another_module_is_allowed(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/UserCalls/CatchesForeignExceptionFactory.php'], []);
    }

    /**
     * The same call with no exemption. Naming the interface is what silences it,
     * so the assertion above cannot pass because the fixture never crossed.
     */
    public function test_a_call_on_another_modules_contract_is_reported_when_not_ignored(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/UserCalls/EnvironmentCallFactory.php'],
            [[
                $this->expectedError('EnvironmentCallFactory', 'Shop\Domain\ShopEnvironmentInterface', 'Shop'),
                18,
                $this->expectedTip('Shop'),
            ]],
        );
    }

    public function test_a_call_on_an_ignored_receiver_is_allowed(): void
    {
        $this->ignoreReceivers = [self::ROOT . '\Shop\Domain\ShopEnvironmentInterface'];

        $this->analyse([__DIR__ . '/Fixture/CrossModule/UserCalls/EnvironmentCallFactory.php'], []);
    }

    /**
     * Registered with nothing but the root namespace, the shape the docs show
     * for a project that keeps its modules one segment down.
     */
    public function test_the_optional_arguments_may_be_left_out(): void
    {
        $this->rule = new CrossModuleMethodCallRule(self::ROOT);

        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/UserCalls/InjectedFactory.php'],
            [[$this->expectedError('InjectedFactory', 'Shop\Domain\ShopService', 'Shop'), 22, $this->expectedTip('Shop')]],
        );
    }

    protected function getRule(): Rule
    {
        return $this->rule ??= new CrossModuleMethodCallRule(
            self::ROOT,
            1,
            $this->sharedNamespaces,
            $this->ignoreReceivers,
        );
    }

    private function expectedTip(string $module): string
    {
        return sprintf("Type-hint %s's Facade, or its interface, instead.", self::ROOT . '\\' . $module);
    }

    private function expectedError(string $caller, string $receiver, string $module): string
    {
        return sprintf(
            'Class %s calls a method on %s from another module (%s). Cross-module access must go through a Facade.',
            self::ROOT . '\UserCalls\\' . $caller,
            self::ROOT . '\\' . $receiver,
            self::ROOT . '\\' . $module,
        );
    }
}
