<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Gacela\Framework\Config;
use Gacela\Framework\Config\ConfigReaderInterface;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();
    }

    public function test_get_undefined_key(): void
    {
        $this->expectExceptionMessageMatches('/Could not find config key "undefined-key"/');
        Config::getInstance()->get('undefined-key');
    }

    public function test_get_default_value_from_undefined_key(): void
    {
        self::assertSame('default', Config::getInstance()->get('undefined-key', 'default'));
    }

    public function test_null_as_default_value_from_undefined_key(): void
    {
        self::assertNull(Config::getInstance()->get('undefined-key', null));
    }

    public function test_get_using_custom_reader(): void
    {
        Config::getInstance()->setGlobalServices([
            'config-readers' => [
                new class () implements ConfigReaderInterface {
                    public function read(string $absolutePath): array
                    {
                        return ['key' => 'value'];
                    }

                    public function canRead(string $absolutePath): bool
                    {
                        return true;
                    }
                },
            ],
        ]);

        self::assertSame('value', Config::getInstance()->get('key'));
    }
}
