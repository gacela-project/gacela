<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use Override;

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

        self::assertStringContainsString('must not depend on', $errors);
        self::assertStringContainsString('fixture: payment must not reach back-office', $errors);
    }

    public function test_the_finding_is_reported_on_the_class_that_creates_the_edge(): void
    {
        self::assertStringContainsString('PaymentFactory.php', $this->analyseFixture());
        self::assertStringNotContainsString('AdminFacade.php', $this->analyseFixture());
    }

    /**
     * The suppression key is public contract, but it is not asserted here: the
     * `raw` error format prints `[identifier=...]` in some PHPStan builds and
     * not others, so this test would be pinning the formatter rather than the
     * rule. It is held instead by DeclaredModuleDependencyAnalyserTest, the
     * ReportedIssues map, and the docs table.
     */
    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/../ModuleRulesFixture/phpstan-module-rules.neon';
    }
}
