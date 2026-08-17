<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\ConfigEnvironmentLayerCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Framework\Config\EnvironmentLayer;
use PHPUnit\Framework\TestCase;

use function array_values;

final class ConfigEnvironmentLayerCheckTest extends TestCase
{
    private const DIRECTORY = 'project' . DIRECTORY_SEPARATOR . 'config';

    public function test_nothing_excluded_is_reported_as_such(): void
    {
        $result = (new ConfigEnvironmentLayerCheck([], []))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no config file is treated as an environment layer of another'], $result->details);
        self::assertSame('', $result->remediation);
    }

    /**
     * A pass with something to say. Warning would fail `doctor --strict` on every
     * project that uses `APP_ENV` at all, which is the arrangement this reports.
     */
    public function test_an_excluded_layer_is_a_pass_that_names_the_file_and_its_base(): void
    {
        $result = (new ConfigEnvironmentLayerCheck($this->layersOf('app.php', 'app-prod.php'), []))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame([
            $this->path('app-prod.php')
                . ' matches a base config path but is excluded from it: an environment layer of '
                . $this->path('app.php')
                . ', read only when APP_ENV=prod',
        ], $result->details);
    }

    /**
     * The one line that helps a project whose file is not an environment layer at
     * all, so it has to be there whenever something was excluded.
     */
    public function test_an_excluded_layer_says_what_to_do_when_it_is_not_one(): void
    {
        $result = (new ConfigEnvironmentLayerCheck($this->layersOf('app.php', 'app-extra.php'), []))->run();

        self::assertStringContainsString('rename it', $result->remediation);
        self::assertStringContainsString('addAppConfig()', $result->remediation);
    }

    /**
     * The chain is `APP_ENV` and then each declared dimension in declaration
     * order, so the segments of the suffix pair off with the variables.
     */
    public function test_a_declared_dimension_is_named_beside_the_environment(): void
    {
        $result = (new ConfigEnvironmentLayerCheck(
            $this->layersOf('app.php', 'app-prod-eu.php'),
            ['APP_REGION'],
        ))->run();

        self::assertStringContainsString('read only when APP_ENV=prod and APP_REGION=eu', $result->details[0]);
    }

    public function test_every_declared_dimension_is_named(): void
    {
        $result = (new ConfigEnvironmentLayerCheck(
            $this->layersOf('app.php', 'app-prod-eu-acme.php'),
            ['APP_REGION', 'APP_TENANT'],
        ))->run();

        self::assertStringContainsString(
            'read only when APP_ENV=prod and APP_REGION=eu and APP_TENANT=acme',
            $result->details[0],
        );
    }

    /**
     * With nothing declared, `app-prod-eu.php` is what `APP_ENV=prod-eu` selects
     * -- a hyphen is legal in an environment name -- so naming a variable this
     * project never declared would be inventing one.
     */
    public function test_a_suffix_longer_than_the_declared_chain_is_reported_as_it_stands(): void
    {
        $result = (new ConfigEnvironmentLayerCheck($this->layersOf('app.php', 'app-prod-eu.php'), []))->run();

        self::assertStringContainsString('the environment chain resolves to "prod-eu"', $result->details[0]);
    }

    public function test_every_excluded_layer_is_named(): void
    {
        $result = (new ConfigEnvironmentLayerCheck(
            $this->layersOf('app.php', 'app-prod.php', 'app-dev.php'),
            [],
        ))->run();

        self::assertCount(2, $result->details);
        self::assertStringContainsString('APP_ENV=prod', $result->details[0]);
        self::assertStringContainsString('APP_ENV=dev', $result->details[1]);
    }

    /**
     * @return list<EnvironmentLayer>
     */
    private function layersOf(string ...$names): array
    {
        return array_values(EnvironmentLayer::within(
            array_map($this->path(...), $names),
        ));
    }

    private function path(string $name): string
    {
        return self::DIRECTORY . DIRECTORY_SEPARATOR . $name;
    }
}
