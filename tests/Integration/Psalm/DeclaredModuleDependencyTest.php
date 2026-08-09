<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

/**
 * The other half of the parity pair: Psalm, over the same fixture and the same
 * rules file {@see \GacelaTest\Integration\PHPStan\DeclaredModuleDependencyTest}
 * hands PHPStan.
 */
final class DeclaredModuleDependencyTest extends PsalmFixtureTestCase
{
    public function test_a_dependency_the_rules_file_denies_is_reported(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaDeclaredModuleDependency', $errors);
        self::assertStringContainsString('fixture: payment must not reach back-office', $errors);
    }

    public function test_the_finding_is_reported_on_the_class_that_creates_the_edge(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaDeclaredModuleDependency', $this->errorsIn('PaymentFactory.php'));
        self::assertSame('', $this->errorsIn('AdminFacade.php'));
    }

    /**
     * Psalm has no separate channel for a tip, so the correction has to ride
     * along in the message -- both hosts should tell you the same thing.
     */
    public function test_the_finding_carries_the_correction(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('or change the rule that forbids it', $errors);
    }

    protected static function configPath(): string
    {
        return __DIR__ . '/../ModuleRulesFixture/psalm-module-rules.xml';
    }
}
