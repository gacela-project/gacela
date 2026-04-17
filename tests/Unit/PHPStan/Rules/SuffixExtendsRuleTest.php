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
                ],
            ],
        );
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
            [__DIR__ . '/Fixture/SuffixFactory/InvalidFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\SuffixFactory\InvalidFactory should extend ' . AbstractFactory::class,
                    7,
                ],
            ],
        );
    }

    public function test_allows_user_factory_extending_abstract_factory(): void
    {
        $this->suffix = 'Factory';
        $this->expectedParent = AbstractFactory::class;

        $this->analyse([__DIR__ . '/Fixture/SuffixFactory/UserFactory.php'], []);
    }

    public function test_reports_bad_provider_suffix(): void
    {
        $this->suffix = 'Provider';
        $this->expectedParent = AbstractProvider::class;

        $this->analyse(
            [__DIR__ . '/Fixture/SuffixProvider/InvalidProvider.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\SuffixProvider\InvalidProvider should extend ' . AbstractProvider::class,
                    7,
                ],
            ],
        );
    }

    public function test_allows_user_provider_extending_abstract_provider(): void
    {
        $this->suffix = 'Provider';
        $this->expectedParent = AbstractProvider::class;

        $this->analyse([__DIR__ . '/Fixture/SuffixProvider/UserProvider.php'], []);
    }

    public function test_reports_bad_config_suffix(): void
    {
        $this->suffix = 'Config';
        $this->expectedParent = AbstractConfig::class;

        $this->analyse(
            [__DIR__ . '/Fixture/SuffixConfig/InvalidConfig.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\SuffixConfig\InvalidConfig should extend ' . AbstractConfig::class,
                    7,
                ],
            ],
        );
    }

    public function test_allows_user_config_extending_abstract_config(): void
    {
        $this->suffix = 'Config';
        $this->expectedParent = AbstractConfig::class;

        $this->analyse([__DIR__ . '/Fixture/SuffixConfig/UserConfig.php'], []);
    }

    protected function getRule(): Rule
    {
        return new SuffixExtendsRule($this->suffix, $this->expectedParent);
    }
}
