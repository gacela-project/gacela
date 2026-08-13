<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\Framework\AbstractFacade;
use Gacela\StaticAnalysis\Rules\FacadeOnlyDelegatesAnalyser;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class FacadeOnlyDelegatesAnalyserTest extends TestCase
{
    public function test_a_single_delegation_is_allowed(): void
    {
        self::assertSame([], $this->analyse('return $this->getFactory()->createThing();'));
    }

    public function test_a_longer_chain_off_the_factory_is_still_a_delegation(): void
    {
        self::assertSame([], $this->analyse('return $this->getFactory()->createThing()->run()->result;'));
    }

    public function test_the_config_and_the_provider_are_delegation_roots_too(): void
    {
        self::assertSame([], $this->analyse('return $this->getConfig()->value();'));
        self::assertSame([], $this->analyse('return $this->getProvider()->thing();'));
    }

    public function test_a_delegation_used_as_a_statement_rather_than_returned(): void
    {
        self::assertSame([], $this->analyse('$this->getFactory()->createThing()->run();'));
    }

    public function test_a_nullsafe_chain_is_a_delegation(): void
    {
        self::assertSame([], $this->analyse('return $this->getFactory()?->createThing();'));
    }

    public function test_inline_logic_is_reported(): void
    {
        $violations = $this->analyse('return 1 + 1;');

        self::assertCount(1, $violations);
        self::assertSame(
            'Facade method App\Checkout\CheckoutFacade::doThing() must only delegate to $this->getFactory()/getConfig()/getProvider()/getResolvedType(); no inline logic allowed.',
            $violations[0]->message,
        );
        self::assertSame('gacela.facadeOnlyDelegates', $violations[0]->identifier);
    }

    /**
     * One delegation plus anything else is not one delegation.
     */
    public function test_a_second_statement_is_reported(): void
    {
        self::assertCount(1, $this->analyse("\$x = 1;\nreturn \$this->getFactory()->createThing();"));
    }

    /**
     * The chain has to start at the facade's own pillar accessor; reaching
     * through a property is the inline logic this forbids.
     */
    public function test_a_chain_rooted_somewhere_else_is_reported(): void
    {
        self::assertCount(1, $this->analyse('return $this->repository->findAll();'));
    }

    public function test_a_call_on_another_object_is_reported(): void
    {
        self::assertCount(1, $this->analyse('return $service->getFactory();'));
    }

    public function test_a_cached_arrow_function_delegation_is_allowed(): void
    {
        self::assertSame([], $this->analyse('return $this->cached(fn () => $this->getFactory()->createThing());'));
    }

    public function test_a_cached_closure_delegation_is_allowed(): void
    {
        self::assertSame([], $this->analyse(
            'return $this->cached(function () { return $this->getFactory()->createThing(); });',
        ));
    }

    public function test_a_cached_closure_doing_more_than_delegating_is_reported(): void
    {
        self::assertCount(1, $this->analyse(
            'return $this->cached(function () { $x = 1; return $this->getFactory()->createThing(); });',
        ));
    }

    public function test_a_cached_call_without_arguments_is_reported(): void
    {
        self::assertCount(1, $this->analyse('return $this->cached();'));
    }

    public function test_a_private_method_may_hold_logic(): void
    {
        self::assertSame([], $this->analyse('return 1 + 1;', 'private'));
    }

    public function test_an_empty_body_is_allowed(): void
    {
        self::assertSame([], $this->analyse(''));
    }

    /**
     * The pillar accessors are how a facade delegates; they are not themselves
     * delegations.
     */
    public function test_the_pillar_accessors_are_not_checked(): void
    {
        $source = <<<'PHP'
            <?php
            final class CheckoutFacade
            {
                public function getFactory()
                {
                    return 1 + 1;
                }
            }
            PHP;

        self::assertSame([], $this->analyseSource($source, 'getFactory'));
    }

    public function test_a_class_that_is_not_a_facade_is_not_checked(): void
    {
        $source = <<<'PHP'
            <?php
            final class CheckoutService
            {
                public function doThing()
                {
                    return 1 + 1;
                }
            }
            PHP;

        $analyser = new FacadeOnlyDelegatesAnalyser();
        $class = new FakeAnalysedClass('App\Checkout\CheckoutService');

        self::assertSame([], $analyser->analyse(ParseSource::methodIn($source, 'doThing'), $class));
    }

    /**
     * A concrete public method with no body is an interface method.
     */
    public function test_a_method_without_a_body_is_not_checked(): void
    {
        $source = <<<'PHP'
            <?php
            interface CheckoutFacadeInterface
            {
                public function doThing();
            }
            PHP;

        self::assertSame([], $this->analyseSource($source, 'doThing'));
    }

    public function test_an_abstract_method_is_not_checked(): void
    {
        $source = <<<'PHP'
            <?php
            abstract class CheckoutFacade
            {
                abstract public function doThing();
            }
            PHP;

        self::assertSame([], $this->analyseSource($source, 'doThing'));
    }

    /**
     * @return list<Violation>
     */
    /**
     * A static method has no `$this` to delegate through, so no body it could
     * hold would satisfy this rule -- and the tip names a call it cannot make.
     * Reporting it leaves a rename or a baseline entry as the only way out,
     * the same reason interfaces, traits and enums go unreported.
     */
    public function test_a_static_method_cannot_delegate_so_it_is_not_judged(): void
    {
        self::assertSame([], $this->analyse('return new self();', 'public static'));
        self::assertSame([], $this->analyse("return 'a literal';", 'public static'));
        self::assertSame([], $this->analyse('$x = 1; return $x + 1;', 'public static'));
    }

    /**
     * Instance methods are unaffected: the guard is about `$this` being
     * unavailable, not about relaxing what a Facade may hold.
     */
    public function test_an_instance_method_with_logic_is_still_reported(): void
    {
        self::assertNotSame([], $this->analyse('$x = 1; return $x + 1;'));
    }

    private function analyse(string $body, string $visibility = 'public'): array
    {
        return $this->analyseSource(
            sprintf("<?php\nfinal class CheckoutFacade\n{\n    %s function doThing()\n    {\n%s\n    }\n}", $visibility, $body),
            'doThing',
        );
    }

    /**
     * @return list<Violation>
     */
    private function analyseSource(string $source, string $methodName): array
    {
        $analyser = new FacadeOnlyDelegatesAnalyser();
        $class = new FakeAnalysedClass('App\Checkout\CheckoutFacade', [AbstractFacade::class]);

        return $analyser->analyse(ParseSource::methodIn($source, $methodName), $class);
    }
}
