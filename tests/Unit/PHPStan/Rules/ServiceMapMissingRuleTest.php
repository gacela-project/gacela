<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\PHPStan\Rules\ServiceMapMissingRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * The rule under the host, rather than the analyser on its own: what the
 * analyser finds is covered by `ServiceMapMissingAnalyserTest`, and this is
 * the proof the finding survives the adapting -- identifier, line and tip
 * included.
 *
 * @extends RuleTestCase<ServiceMapMissingRule>
 */
final class ServiceMapMissingRuleTest extends RuleTestCase
{
    public function test_an_accessor_resolved_from_the_docblock_is_reported(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/ServiceMapMissing/DocBlockCommand.php'],
            [
                [
                    \GacelaTest\Unit\PHPStan\Rules\Fixture\ServiceMapMissing\DocBlockCommand::class . '::getFacade() '
                    . 'is resolved from its @method docblock, which is deprecated and removed in 3.0',
                    12,
                    "Declare it with #[ServiceMap(method: 'getFacade', className: WalletFacade::class)].",
                ],
            ],
        );
    }

    /**
     * Keeping the `@method` tag alongside the attribute is the ordinary state
     * of a migrated class -- editors read the tag, the runtime reads the
     * attribute -- so it must not be reported.
     */
    public function test_an_accessor_the_attribute_declares_is_not_reported(): void
    {
        $this->analyse([__DIR__ . '/Fixture/ServiceMapMissing/DeclaredCommand.php'], []);
    }

    protected function getRule(): Rule
    {
        return new ServiceMapMissingRule();
    }
}
