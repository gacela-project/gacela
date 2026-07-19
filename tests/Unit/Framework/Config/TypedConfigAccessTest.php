<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Exception\ConfigException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class TypedConfigAccessTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
            $config->addAppConfigKeyValues([
                'a-string' => 'hello',
                'an-int' => 42,
                'a-float' => 3.14,
                'a-bool' => true,
                'an-array' => ['x' => 1],
            ]);
        });
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    public function test_get_string_returns_typed_value(): void
    {
        self::assertSame('hello', Config::getInstance()->getString('a-string'));
    }

    public function test_get_int_returns_typed_value(): void
    {
        self::assertSame(42, Config::getInstance()->getInt('an-int'));
    }

    public function test_get_float_returns_typed_value(): void
    {
        self::assertSame(3.14, Config::getInstance()->getFloat('a-float'));
    }

    public function test_get_float_accepts_int_via_widening(): void
    {
        self::assertSame(42.0, Config::getInstance()->getFloat('an-int'));
    }

    public function test_get_bool_returns_typed_value(): void
    {
        self::assertTrue(Config::getInstance()->getBool('a-bool'));
    }

    public function test_get_array_returns_typed_value(): void
    {
        self::assertSame(['x' => 1], Config::getInstance()->getArray('an-array'));
    }

    public function test_get_int_throws_on_wrong_type(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected "int"');

        Config::getInstance()->getInt('a-string');
    }

    public function test_get_string_throws_on_wrong_type(): void
    {
        $this->expectException(ConfigException::class);

        Config::getInstance()->getString('an-int');
    }

    public function test_get_bool_does_not_coerce_int(): void
    {
        $this->expectException(ConfigException::class);

        Config::getInstance()->getBool('an-int');
    }

    public function test_missing_key_without_default_throws(): void
    {
        $this->expectException(ConfigException::class);

        Config::getInstance()->getString('missing-key');
    }

    public function test_missing_key_returns_default_when_provided(): void
    {
        self::assertSame('fallback', Config::getInstance()->getString('missing-key', 'fallback'));
        self::assertSame(7, Config::getInstance()->getInt('missing-key', 7));
        self::assertSame(1.5, Config::getInstance()->getFloat('missing-key', 1.5));
        self::assertFalse(Config::getInstance()->getBool('missing-key', false));
        self::assertSame([], Config::getInstance()->getArray('missing-key', []));
    }

    public function test_missing_key_multi_item_array_default_is_returned_unchanged(): void
    {
        self::assertSame(
            ['a' => 1, 'b' => 2],
            Config::getInstance()->getArray('missing-key', ['a' => 1, 'b' => 2]),
        );
    }

    public function test_get_float_throws_on_wrong_type(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected "float"');

        Config::getInstance()->getFloat('a-string');
    }

    public function test_get_array_throws_on_wrong_type(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected "array"');

        Config::getInstance()->getArray('an-int');
    }

    public function test_get_int_missing_key_without_default_throws(): void
    {
        $this->expectException(ConfigException::class);

        Config::getInstance()->getInt('missing-key');
    }

    public function test_get_float_missing_key_without_default_throws(): void
    {
        $this->expectException(ConfigException::class);

        Config::getInstance()->getFloat('missing-key');
    }

    public function test_get_bool_missing_key_without_default_throws(): void
    {
        $this->expectException(ConfigException::class);

        Config::getInstance()->getBool('missing-key');
    }

    public function test_get_array_missing_key_without_default_throws(): void
    {
        $this->expectException(ConfigException::class);

        Config::getInstance()->getArray('missing-key');
    }

    public function test_typed_getters_initialize_config_on_first_access(): void
    {
        // Fresh Config with no prior init(): each typed getter must trigger the
        // lazy initialization itself, otherwise the key would not be found.
        foreach ([['getString', 'hello'], ['getInt', 42], ['getFloat', 3.14], ['getBool', true], ['getArray', ['x' => 1]]] as [$getter, $expected]) {
            Config::resetInstance();
            $config = Config::createWithSetup(
                (new SetupGacela())
                    ->setFileCacheEnabled(false)
                    ->setConfigKeyValues([
                        'a-string' => 'hello',
                        'an-int' => 42,
                        'a-float' => 3.14,
                        'a-bool' => true,
                        'an-array' => ['x' => 1],
                    ]),
            );
            $config->setAppRootDir(__DIR__);

            $key = ['getString' => 'a-string', 'getInt' => 'an-int', 'getFloat' => 'a-float', 'getBool' => 'a-bool', 'getArray' => 'an-array'][$getter];
            self::assertSame($expected, $config->{$getter}($key), "first access via {$getter}()");
        }
    }
}
