<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use Override;

/**
 * Runs PHPStan for real over a Facade whose interface has drifted from it.
 *
 * `FacadeInterfaceInSyncRule` is registered uncommented in
 * `phpstan-gacela.neon`, so every consumer runs it, and nothing drove its
 * PHPStan front end -- found the same way as {@see SuffixExtendsTest}, by
 * silencing each analyser and seeing which tests noticed.
 */
final class FacadeInterfaceInSyncTest extends PhpStanFixtureTestCase
{
    public function test_a_public_facade_method_missing_from_the_interface_is_reported(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringContainsString('SyncFixtureFacade::driftedAway() is missing from', $errors);
        self::assertStringContainsString('SyncFixtureFacadeInterface', $errors);
    }

    /**
     * The consequence, which is the reason to act on it: the method exists and
     * is simply unreachable through the type consumers were told to use.
     */
    public function test_the_finding_says_who_cannot_reach_it(): void
    {
        self::assertStringContainsString(
            'Consumers type-hinting the interface cannot reach it',
            $this->analyseFixture(),
        );
    }

    /**
     * The method names are deliberately not `declared`/`undeclared`: one is a
     * substring of the other, so this assertion would have held however the
     * rule behaved.
     */
    public function test_a_method_the_interface_declares_is_left_alone(): void
    {
        self::assertStringNotContainsString('inSync() is missing', $this->analyseFixture());
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/InterfaceSyncFixture/phpstan-interfacesyncfixture.neon';
    }
}
