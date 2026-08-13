<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\Rules\ServiceMapMissingAnalyser;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

final class ServiceMapMissingAnalyserTest extends TestCase
{
    public function test_a_method_docblock_without_the_attribute_is_reported(): void
    {
        $violations = $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                /** @method WalletFacade getFacade() */
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        );

        self::assertCount(1, $violations);
        self::assertSame(
            'App\Wallet\WalletCommand::getFacade() is resolved from its @method docblock, '
            . 'which is deprecated and removed in 3.0',
            $violations[0]->message,
        );
        self::assertSame('gacela.serviceMapMissing', $violations[0]->identifier);
    }

    /**
     * The type is repeated as the docblock spells it. The suggested line goes
     * back into the file it came from, where the import that makes the short
     * name resolve is the very reason the fallback worked -- qualifying it here
     * would produce a name that file cannot resolve any better.
     */
    public function test_the_tip_is_the_attribute_to_paste(): void
    {
        $violations = $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                /** @method WalletFacade getFacade() */
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        );

        self::assertSame(
            "Declare it with #[ServiceMap(method: 'getFacade', className: WalletFacade::class)].",
            $violations[0]->tip,
        );
    }

    public function test_the_attribute_silences_the_accessor_it_declares(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                use Gacela\Framework\ServiceResolver\ServiceMap;
                /** @method WalletFacade getFacade() */
                #[ServiceMap(method: 'getFacade', className: WalletFacade::class)]
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        ));
    }

    /**
     * `method` is the attribute's first parameter, so a positional declaration
     * is the same declaration. Reading only named arguments would report a
     * class that is already migrated.
     */
    public function test_a_positional_attribute_declares_the_accessor_too(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                use Gacela\Framework\ServiceResolver\ServiceMap;
                /** @method WalletFacade getFacade() */
                #[ServiceMap('getFacade', WalletFacade::class)]
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        ));
    }

    /**
     * The attribute is repeatable and one class commonly declares several
     * accessors. Only the undeclared one is reported.
     */
    public function test_only_the_accessor_the_attributes_do_not_cover_is_reported(): void
    {
        $violations = $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                use Gacela\Framework\ServiceResolver\ServiceMap;
                /**
                 * @method WalletFacade getFacade()
                 * @method WalletRepository getRepository()
                 */
                #[ServiceMap(method: 'getFacade', className: WalletFacade::class)]
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        );

        self::assertCount(1, $violations);
        self::assertStringContainsString('getRepository()', $violations[0]->message);
    }

    /**
     * Every undeclared accessor is reported, not the first one -- a class
     * migrates accessor by accessor, and a report that stopped at one would
     * send you back for the rest.
     */
    public function test_every_undeclared_accessor_is_reported(): void
    {
        $violations = $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                /**
                 * @method WalletFacade getFacade()
                 * @method WalletRepository getRepository()
                 */
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        );

        self::assertCount(2, $violations);
        self::assertStringContainsString('getFacade()', $violations[0]->message);
        self::assertStringContainsString('getRepository()', $violations[1]->message);
    }

    /**
     * Every attribute is read, not the first: declaring two accessors is the
     * ordinary shape of a migrated class.
     */
    public function test_every_attribute_declares_its_accessor(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                use Gacela\Framework\ServiceResolver\ServiceMap;
                /**
                 * @method WalletFacade getFacade()
                 * @method WalletRepository getRepository()
                 */
                #[ServiceMap(method: 'getFacade', className: WalletFacade::class)]
                #[ServiceMap(method: 'getRepository', className: WalletRepository::class)]
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        ));
    }

    /**
     * Named arguments may be written in any order, so `method:` is found by its
     * name rather than by where it sits.
     */
    public function test_the_named_arguments_may_be_written_in_either_order(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                use Gacela\Framework\ServiceResolver\ServiceMap;
                /** @method WalletFacade getFacade() */
                #[ServiceMap(className: WalletFacade::class, method: 'getFacade')]
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        ));
    }

    /**
     * `className` takes a string, so it may be written as a plain one rather
     * than `::class`. Written first, it is still not the method name -- reading
     * whichever argument happens to be a string would file the accessor under a
     * class name and report the class as unmigrated.
     */
    public function test_a_string_class_name_written_first_is_not_the_method_name(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                use Gacela\Framework\ServiceResolver\ServiceMap;
                /** @method WalletFacade getFacade() */
                #[ServiceMap(className: 'App\Wallet\WalletFacade', method: 'getFacade')]
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        ));
    }

    /**
     * A real method is skipped, and the walk continues: an undeclared accessor
     * after it is still reported. Skipping by leaving the loop would hide
     * whatever the class documents next.
     */
    public function test_a_real_method_does_not_end_the_walk(): void
    {
        $violations = $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                /**
                 * @method WalletFacade getFacade()
                 * @method WalletRepository getRepository()
                 */
                final class WalletCommand {
                    use ServiceResolverAwareTrait;
                    public function getFacade(): WalletFacade { return new WalletFacade(); }
                }
                PHP,
        );

        self::assertCount(1, $violations);
        self::assertStringContainsString('getRepository()', $violations[0]->message);
    }

    /**
     * `__call()` is only reached for a method the class does not have, so a
     * declared one is never resolved from the docblock -- its `@method` tag is
     * documentation, and reporting it would be advice with nothing to fix.
     */
    public function test_a_real_method_is_not_resolved_from_the_docblock(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                /** @method WalletFacade getFacade() */
                final class WalletCommand {
                    use ServiceResolverAwareTrait;
                    public function getFacade(): WalletFacade { return new WalletFacade(); }
                }
                PHP,
        ));
    }

    /**
     * The trait is what routes an unknown method into the deprecated resolver.
     * Without it the class has its own `__call`, or none, and either way this
     * rule has nothing to say -- it runs inside every consumer's build, where
     * `@method` on a non-Gacela class is an ordinary thing to write.
     */
    public function test_a_class_not_using_the_trait_is_not_checked(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                /** @method WalletFacade getFacade() */
                final class WalletCommand {}
                PHP,
        ));
    }

    public function test_a_class_without_a_docblock_is_not_checked(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        ));
    }

    /**
     * The parameter list is optional in what the resolver accepts -- it matches
     * the name at a word boundary -- so a tag written without one resolves at
     * runtime and has to be reported here too. Requiring it would make the rule
     * quietly pass a class that is not ready.
     */
    public function test_a_method_tag_without_a_parameter_list_is_still_reported(): void
    {
        $violations = $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                /** @method WalletFacade getFacade */
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        );

        self::assertCount(1, $violations);
        self::assertStringContainsString('getFacade()', $violations[0]->message);
    }

    /**
     * A tag with no return type states nothing to declare, so there is no
     * attribute to suggest and it is left alone rather than reported without a
     * correction.
     */
    public function test_a_method_tag_without_a_type_is_left_alone(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use Gacela\Framework\ServiceResolverAwareTrait;
                /** @method getFacade() */
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        ));
    }

    /**
     * Psalm leaves the short name on the node and puts the qualified one on a
     * `resolvedName` attribute; PHPStan rewrites the node. Reading only
     * `toString()` matches the trait under one host and nothing under the
     * other.
     */
    public function test_the_trait_is_recognised_under_both_hosts(): void
    {
        $php = <<<'PHP'
            <?php
            namespace App\Wallet;
            use Gacela\Framework\ServiceResolverAwareTrait;
            /** @method WalletFacade getFacade() */
            final class WalletCommand { use ServiceResolverAwareTrait; }
            PHP;

        $analyser = new ServiceMapMissingAnalyser();
        $class = new FakeAnalysedClass('App\Wallet\WalletCommand');

        self::assertCount(1, $analyser->analyse(ParseSource::classInAsPhpStanResolves($php), $class));
        self::assertCount(1, $analyser->analyse(ParseSource::classInWithNameAttributes($php), $class));
    }

    /**
     * A trait of the consumer's own that happens to be imported alongside is
     * not the one that resolves pillars.
     */
    public function test_another_trait_does_not_stand_in_for_the_resolver(): void
    {
        self::assertSame([], $this->analyse(
            <<<'PHP'
                <?php
                namespace App\Wallet;
                use App\Wallet\ServiceResolverAwareTrait;
                /** @method WalletFacade getFacade() */
                final class WalletCommand { use ServiceResolverAwareTrait; }
                PHP,
        ));
    }

    /**
     * @return list<Violation>
     */
    private function analyse(string $php): array
    {
        return (new ServiceMapMissingAnalyser())->analyse(
            ParseSource::classInAsPhpStanResolves($php),
            new FakeAnalysedClass('App\Wallet\WalletCommand'),
        );
    }
}
