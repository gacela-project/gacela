<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ConfigSchema;

use Closure;
use Gacela\Console\Infrastructure\Command\DebugConfigCommand;
use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Schema\ConfigType;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The three commands that read the declared schema, against the same
 * declarations. They answer for different audiences -- a CI gate, a deploy
 * gate, a person looking at a table -- and a schema that failed one and passed
 * another would be worse than no schema.
 */
final class ConfigSchemaCommandsTest extends TestCase
{
    public function test_validate_config_fails_on_a_required_key_no_source_provides(): void
    {
        $tester = $this->execute(new ValidateConfigCommand(), static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['db.dsn' => ConfigType::string()->required()]);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('db.dsn', $tester->getDisplay());
        self::assertStringContainsString('is required but missing', $tester->getDisplay());
    }

    public function test_validate_config_fails_on_a_value_of_the_wrong_type(): void
    {
        $tester = $this->execute(new ValidateConfigCommand(), static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['retries' => ConfigType::int()->required()]);
            $config->addAppConfigKeyValue('retries', 'three');
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('declared int but is string', $tester->getDisplay());
    }

    public function test_validate_config_passes_when_the_declaration_is_satisfied(): void
    {
        $tester = $this->execute(new ValidateConfigCommand(), static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['db.dsn' => ConfigType::string()->required()]);
            $config->addAppConfigKeyValue('db.dsn', 'sqlite::memory:');
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 declared key(s), all satisfied', $tester->getDisplay());
    }

    /**
     * A green line about a check that never ran is worse than no line.
     */
    public function test_validate_config_says_no_schema_is_declared_rather_than_passing_one(): void
    {
        $tester = $this->execute(new ValidateConfigCommand(), static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue('anything', 'goes');
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No schema declared', $tester->getDisplay());
    }

    public function test_doctor_reports_the_unsatisfied_declaration(): void
    {
        $tester = $this->execute(new DoctorCommand(), static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['db.dsn' => ConfigType::string()->required()]);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('config schema', $tester->getDisplay());
        self::assertStringContainsString('db.dsn', $tester->getDisplay());
    }

    public function test_doctor_counts_the_keys_it_checked(): void
    {
        $tester = $this->execute(new DoctorCommand(), static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['db.dsn' => ConfigType::string()->required()]);
            $config->addAppConfigKeyValue('db.dsn', 'sqlite::memory:');
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 declared key(s), all satisfied', $tester->getDisplay());
    }

    public function test_doctor_stays_quiet_when_nothing_is_declared(): void
    {
        $tester = $this->execute(new DoctorCommand(), static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue('anything', 'goes');
        });

        self::assertStringContainsString('no schema declared', $tester->getDisplay());
    }

    public function test_debug_config_marks_declared_undeclared_and_missing_keys(): void
    {
        $tester = $this->execute(new DebugConfigCommand(), static function (GacelaConfig $config): void {
            $config->declareConfigSchema([
                'declared.key' => ConfigType::string()->required(),
                'absent.key' => ConfigType::string()->required(),
            ]);
            $config->addAppConfigKeyValue('declared.key', 'here');
            $config->addAppConfigKeyValue('undeclared.key', 'also here');
        });

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/declared\.key\s*\|\s*here\s*\|\s*declared/', $display);
        self::assertMatchesRegularExpression('/undeclared\.key\s*\|\s*also here\s*\|\s*undeclared/', $display);
        self::assertMatchesRegularExpression('/absent\.key\s*\|.*\|\s*missing/', $display);
    }

    /**
     * A declared default is not a missing key: it is the value the declaration
     * itself provides.
     */
    public function test_debug_config_shows_a_defaulted_key_as_a_value(): void
    {
        $tester = $this->execute(new DebugConfigCommand(), static function (GacelaConfig $config): void {
            $config->declareConfigSchema(['retries' => ConfigType::int()->default(3)]);
        });

        self::assertMatchesRegularExpression('/retries\s*\|\s*3\s*\|\s*declared/', $tester->getDisplay());
    }

    private function execute(Command $command, Closure $configFn): CommandTester
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $configFn($config);
        });

        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }
}
