<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use Override;

use function str_contains;

/**
 * Runs PHPStan for real over the same fixture and the same rules file that
 * {@see \GacelaTest\Integration\Psalm\DeclaredModuleDependencyTest} hands Psalm.
 *
 * The pair is the test. One rules file feeds `debug:graph --check --rules` and
 * both analysers, and a boundary that held in one host and not the other would
 * be a boundary nobody trusts.
 */
final class DeclaredModuleDependencyTest extends PhpStanFixtureTestCase
{
    public function test_a_dependency_the_rules_file_denies_is_reported(): void
    {
        $errors = $this->analyseFixture();

        self::assertTrue(
            str_contains($errors, 'must not depend on')
            && str_contains($errors, 'fixture: payment must not reach back-office'),
            'PHPStan did not report the denied dependency. Output: ' . $errors,
        );
    }

    public function test_the_finding_is_reported_on_the_class_that_creates_the_edge(): void
    {
        self::assertStringContainsString('PaymentFactory.php', $this->analyseFixture());
        self::assertStringNotContainsString('AdminFacade.php', $this->analyseFixture());
    }

    /**
     * The suppression key is public contract: a consumer writes it into
     * `ignoreErrors` to turn this rule off.
     */
    public function test_the_finding_carries_its_identifier(): void
    {
        self::assertStringContainsString('gacela.declaredModuleDependency', $this->analyseFixture());
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/../ModuleRulesFixture/phpstan-module-rules.neon';
    }
}
