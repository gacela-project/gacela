<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use Gacela\Framework\AbstractFacade;
use Override;

/**
 * Runs PHPStan for real over a class named like a pillar that is not one.
 *
 * `SuffixExtendsRule` is registered four times uncommented in
 * `phpstan-gacela.neon` -- once per pillar -- so it is the rule a consumer is
 * most likely to meet, and nothing drove its PHPStan front end. Found by
 * silencing each analyser in turn and seeing which of these tests noticed:
 * this one and {@see FacadeInterfaceInSyncTest} noticed nothing.
 *
 * What a unit test cannot reach is the adaptation, and here also the wiring:
 * the rule takes its suffix and expected parent as constructor arguments from
 * the neon, so a registration naming the wrong parent would analyse clean.
 */
final class SuffixExtendsTest extends PhpStanFixtureTestCase
{
    public function test_a_class_named_like_a_pillar_that_is_not_one_is_reported(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringContainsString('StraySuffixFacade should extend', $errors);
        self::assertStringContainsString(AbstractFacade::class, $errors);
    }

    /**
     * The registration is four rules, not one, and each carries its own suffix
     * and parent. A fixture that only exercised `Facade` would leave the other
     * three able to name a parent nothing checks.
     */
    public function test_a_pillar_that_does_extend_its_base_is_left_alone(): void
    {
        self::assertStringNotContainsString('ProperSuffixFactory', $this->analyseFixture());
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/SuffixFixture/phpstan-suffixfixture.neon';
    }
}
