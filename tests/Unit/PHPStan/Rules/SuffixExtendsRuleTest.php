<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\PHPStan\Rules\SuffixExtendsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

use function sprintf;

/**
 * @extends RuleTestCase<SuffixExtendsRule>
 */
final class SuffixExtendsRuleTest extends RuleTestCase
{
    private string $suffix = 'Facade';

    private string $expectedParent = AbstractFacade::class;

    public function test_reports_bad_facade_suffix_not_extending_abstract_facade(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/SuffixFacade/BadFacade.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\SuffixFacade\BadFacade should extend ' . AbstractFacade::class,
                    7,
                    $this->expectedTip(),
                ],
            ],
        );
    }

    /**
     * A class with a parent of its own cannot extend the pillar too, so
     * reporting it produces advice whose only resolution is a rename or a
     * baseline entry. Its whole ancestry is still checked for the pillar
     * first, so this only skips a genuinely different hierarchy.
     */
    public function test_ignores_a_class_that_already_extends_something_else(): void
    {
        $this->analyse([__DIR__ . '/Fixture/SuffixFacade/InheritedFacade.php'], []);
    }

    public function test_allows_user_facade_extending_abstract_facade(): void
    {
        $this->analyse([__DIR__ . '/Fixture/SuffixFacade/UserFacade.php'], []);
    }

    public function test_ignores_class_without_facade_suffix(): void
    {
        $this->analyse([__DIR__ . '/Fixture/SuffixFacade/Service.php'], []);
    }

    public function test_reports_bad_factory_suffix(): void
    {
        $this->suffix = 'Factory';
        $this->expectedParent = AbstractFactory::class;

        $this->analyse(
            [__DIR__ . '/Fixture/Invalid/InvalidFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\Invalid\InvalidFactory should extend ' . AbstractFactory::class,
                    7,
                    $this->expectedTip(),
                ],
            ],
        );
    }

    public function test_allows_user_factory_extending_abstract_factory(): void
    {
        $this->suffix = 'Factory';
        $this->expectedParent = AbstractFactory::class;

        $this->analyse([__DIR__ . '/Fixture/User/UserFactory.php'], []);
    }

    public function test_reports_bad_provider_suffix(): void
    {
        $this->suffix = 'Provider';
        $this->expectedParent = AbstractProvider::class;

        $this->analyse(
            [__DIR__ . '/Fixture/Invalid/InvalidProvider.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\Invalid\InvalidProvider should extend ' . AbstractProvider::class,
                    7,
                    $this->expectedTip(),
                ],
            ],
        );
    }

    public function test_allows_user_provider_extending_abstract_provider(): void
    {
        $this->suffix = 'Provider';
        $this->expectedParent = AbstractProvider::class;

        $this->analyse([__DIR__ . '/Fixture/User/UserProvider.php'], []);
    }

    public function test_reports_bad_config_suffix(): void
    {
        $this->suffix = 'Config';
        $this->expectedParent = AbstractConfig::class;

        $this->analyse(
            [__DIR__ . '/Fixture/Invalid/InvalidConfig.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\Invalid\InvalidConfig should extend ' . AbstractConfig::class,
                    7,
                    $this->expectedTip(),
                ],
            ],
        );
    }

    public function test_allows_user_config_extending_abstract_config(): void
    {
        $this->suffix = 'Config';
        $this->expectedParent = AbstractConfig::class;

        $this->analyse([__DIR__ . '/Fixture/User/UserConfig.php'], []);
    }

    public function test_ignores_a_config_extender_named_config(): void
    {
        $this->suffix = 'Config';
        $this->expectedParent = AbstractConfig::class;

        $this->analyse([__DIR__ . '/Fixture/Extender/ExtenderConfig.php'], []);
    }

    protected function getRule(): Rule
    {
        return new SuffixExtendsRule($this->suffix, $this->expectedParent);
    }

    private function expectedTip(): string
    {
        return sprintf(
            "Rename it so it does not end in %s. Extend %s only if it is its module's %s.",
            $this->suffix,
            $this->expectedParent,
            $this->suffix,
        );
    }
}
