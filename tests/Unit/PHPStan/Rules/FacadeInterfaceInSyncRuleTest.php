<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\PHPStan\Rules\FacadeInterfaceInSyncRule;
use GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeInterface\DriftedFacade;
use GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeInterface\DriftedFacadeInterface;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use function sprintf;

/**
 * @extends RuleTestCase<FacadeInterfaceInSyncRule>
 */
final class FacadeInterfaceInSyncRuleTest extends RuleTestCase
{
    public function test_allows_a_facade_whose_interface_declares_every_public_method(): void
    {
        $this->analyse([__DIR__ . '/Fixture/FacadeInterface/InSyncFacade.php'], []);
    }

    public function test_reports_every_public_method_missing_from_the_interface(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/FacadeInterface/DriftedFacade.php'],
            [
                [$this->expectedError('addedLaterAndForgotten'), 25],
                [$this->expectedError('alsoForgotten'), 30],
            ],
        );
    }

    public function test_skips_a_facade_implementing_an_unrelated_interface(): void
    {
        $this->analyse([__DIR__ . '/Fixture/FacadeInterface/UnrelatedInterfaceFacade.php'], []);
    }

    public function test_skips_a_class_that_is_not_a_facade(): void
    {
        $this->analyse([__DIR__ . '/Fixture/FacadeInterface/NotAFacade.php'], []);
    }

    protected function getRule(): Rule
    {
        return new FacadeInterfaceInSyncRule();
    }

    private function expectedError(string $method): string
    {
        return sprintf(
            'Facade method %s::%s() is missing from %s. Consumers type-hinting the interface cannot reach it: declare it in the interface, or make the method non-public.',
            DriftedFacade::class,
            $method,
            DriftedFacadeInterface::class,
        );
    }
}
