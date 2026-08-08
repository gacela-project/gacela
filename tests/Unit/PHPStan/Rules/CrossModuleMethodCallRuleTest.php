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
            [[$this->expectedError('InjectedFactory', 'Shop\Domain\ShopService', 'Shop'), 22]],
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
            [[$this->expectedError('SharedCallFactory', 'Shared\Clock', 'Shared'), 18]],
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
     * Registered with nothing but the root namespace, the shape the docs show
     * for a project that keeps its modules one segment down.
     */
    public function test_the_optional_arguments_may_be_left_out(): void
    {
        $this->rule = new CrossModuleMethodCallRule(self::ROOT);

        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/UserCalls/InjectedFactory.php'],
            [[$this->expectedError('InjectedFactory', 'Shop\Domain\ShopService', 'Shop'), 22]],
        );
    }

    protected function getRule(): Rule
    {
        return $this->rule ??= new CrossModuleMethodCallRule(self::ROOT, 1, $this->sharedNamespaces);
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
