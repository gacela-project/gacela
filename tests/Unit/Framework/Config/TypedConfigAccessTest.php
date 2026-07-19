<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
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
        self::assertFalse(Config::getInstance()->getBool('missing-key', false));
        self::assertSame([], Config::getInstance()->getArray('missing-key', []));
    }
}
