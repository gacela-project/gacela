<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Gacela\Framework\Config;
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
}
