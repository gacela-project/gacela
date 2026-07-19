<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Config\AppEnv;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

final class AppEnvTest extends TestCase
{
    private ?string $originalAppEnv = null;

    protected function setUp(): void
    {
        $env = getenv('APP_ENV');
        $this->originalAppEnv = $env === false ? null : $env;
    }

    protected function tearDown(): void
    {
        putenv($this->originalAppEnv === null ? 'APP_ENV' : 'APP_ENV=' . $this->originalAppEnv);
    }

    public function test_returns_the_app_env_value(): void
    {
        putenv('APP_ENV=prod');

        self::assertSame('prod', AppEnv::current());
    }

    public function test_returns_empty_string_when_unset(): void
    {
        putenv('APP_ENV');

        self::assertSame('', AppEnv::current());
    }

    public function test_returns_empty_string_when_set_to_empty(): void
    {
        putenv('APP_ENV=');

        self::assertSame('', AppEnv::current());
    }
}
