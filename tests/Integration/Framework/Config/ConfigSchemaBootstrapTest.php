<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\Schema\ConfigType;
use Gacela\Framework\Exception\ConfigException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class ConfigSchemaBootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
    }

    public function test_a_declared_default_fills_a_key_no_source_provides(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['retries' => ConfigType::int()->default(3)]);
        });

        self::assertSame(3, Config::getInstance()->getInt('retries'));
    }

    public function test_a_provided_value_wins_over_the_declared_default(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['retries' => ConfigType::int()->default(3)]);
            $config->addAppConfigKeyValue('retries', 7);
        });

        self::assertSame(7, Config::getInstance()->getInt('retries'));
    }

    public function test_nothing_is_checked_while_booting_by_default(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['db.dsn' => ConfigType::string()->required()]);
        });

        // The declaration is unsatisfied, and booting is not where that is
        // reported: validate:config and doctor are.
        self::assertCount(1, Config::getInstance()->configSchemaViolations());
    }

    public function test_the_boot_check_fails_loudly_when_it_is_asked_for(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('db.dsn');

        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['db.dsn' => ConfigType::string()->required()]);
            $config->validateConfigSchemaOnBoot();
        });

        Config::getInstance()->init();
    }

    public function test_the_boot_check_stays_quiet_when_the_declaration_is_satisfied(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['db.dsn' => ConfigType::string()->required()]);
            $config->addAppConfigKeyValue('db.dsn', 'sqlite::memory:');
            $config->validateConfigSchemaOnBoot();
        });

        Config::getInstance()->init();

        self::assertSame('sqlite::memory:', Config::getInstance()->getString('db.dsn'));
    }

    public function test_declaring_twice_merges_per_key(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['a' => ConfigType::string()->required()]);
            $config->declareConfigSchema(['b' => ConfigType::int()->default(1)]);
        });

        self::assertSame(['a', 'b'], Config::getInstance()->configSchema()->declaredKeys());
    }

    public function test_the_later_declaration_of_one_key_wins(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['retries' => ConfigType::string()->required()]);
            $config->declareConfigSchema(['retries' => ConfigType::int()->default(3)]);
        });

        self::assertSame(3, Config::getInstance()->getInt('retries'));
        self::assertSame([], Config::getInstance()->configSchemaViolations());
    }

    public function test_a_violation_names_the_key_and_both_types(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['retries' => ConfigType::int()->required()]);
            $config->addAppConfigKeyValue('retries', 'three');
        });

        $violations = Config::getInstance()->configSchemaViolations();

        self::assertCount(1, $violations);
        self::assertSame('retries', $violations[0]->key);
        self::assertStringContainsString('int', $violations[0]->message);
        self::assertStringContainsString('string', $violations[0]->message);
    }

    public function test_a_project_that_declares_nothing_is_unaffected(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue('anything', 'goes');
        });

        self::assertTrue(Config::getInstance()->configSchema()->isEmpty());
        self::assertSame([], Config::getInstance()->configSchemaViolations());
    }

    private function bootstrap(callable $configFn): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $configFn($config);
        });
    }
}
