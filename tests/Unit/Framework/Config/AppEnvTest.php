<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Config\AppEnv;
use Gacela\Framework\Exception\ConfigDimensionException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * `APP_ENV` is the first link of the dimension chain and reaches exactly
     * what the declared ones reach: the `config/app-{env}.php` glob and the
     * merged-config cache filename. A declared dimension with one of these
     * values is refused; this one was not, so the value went straight into a
     * path.
     *
     * Observed before the check: `APP_ENV=../escaped` had the merged-config
     * cache written into a directory named `gacela-merged-config-<hash>-..`,
     * and `APP_ENV=x/../../pwned` made the write fail silently, so the
     * application booted uncached every request with nothing to say why.
     */
    #[DataProvider('rejectedValuesProvider')]
    public function test_a_value_that_cannot_reach_a_path_is_refused(string $value): void
    {
        putenv('APP_ENV=' . $value);

        $this->expectException(ConfigDimensionException::class);
        $this->expectExceptionMessage('APP_ENV');

        AppEnv::current();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedValuesProvider(): iterable
    {
        yield 'parent directory' => ['../escaped'];
        yield 'traversal out of the cache dir' => ['x/../../pwned'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'glob wildcard' => ['eu*'];
        yield 'space' => ['a b'];
    }

    #[DataProvider('acceptedValuesProvider')]
    public function test_an_ordinary_environment_name_is_accepted(string $value): void
    {
        putenv('APP_ENV=' . $value);

        self::assertSame($value, AppEnv::current());
    }

    /**
     * The characters a dimension already allows, so the two agree on what a
     * name is: `staging-eu` and `1.2` are as legitimate here as there.
     *
     * @return iterable<string, array{string}>
     */
    public static function acceptedValuesProvider(): iterable
    {
        yield 'plain' => ['prod'];
        yield 'hyphen' => ['staging-eu'];
        yield 'underscore' => ['local_dev'];
        yield 'dot' => ['1.2'];
    }
}
