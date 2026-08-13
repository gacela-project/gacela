<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge;

use Gacela\LaravelBridge\Configuration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class ConfigurationTest extends TestCase
{
    public function test_an_empty_config_is_the_defaults(): void
    {
        $config = Configuration::validate([]);

        self::assertSame([
            'enabled' => true,
            'app_root_dir' => null,
            'cache_dir' => null,
            'file_cache' => null,
            'project_namespaces' => [],
            'external_services' => [],
            'register_commands' => true,
            'command_prefix' => 'gacela:',
        ], $config);
    }

    public function test_a_full_config_passes_through(): void
    {
        $config = Configuration::validate([
            'enabled' => false,
            'app_root_dir' => '/srv/app',
            'cache_dir' => '/tmp/gacela',
            'file_cache' => true,
            'project_namespaces' => ['App'],
            'external_services' => ['logger' => 'log'],
            'register_commands' => false,
            'command_prefix' => 'g:',
        ]);

        self::assertFalse($config['enabled']);
        self::assertSame('/srv/app', $config['app_root_dir']);
        self::assertSame('/tmp/gacela', $config['cache_dir']);
        self::assertTrue($config['file_cache']);
        self::assertSame(['App'], $config['project_namespaces']);
        self::assertSame(['logger' => 'log'], $config['external_services']);
        self::assertFalse($config['register_commands']);
        self::assertSame('g:', $config['command_prefix']);
    }

    public function test_an_unknown_key_fails_naming_the_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cache_dri');

        Configuration::validate(['cache_dri' => '/tmp/gacela']);
    }

    /**
     * `cache_dri` for `cache_dir` is the shape a typo takes, and eight allowed
     * keys is a list to scan rather than an answer. Symfony's own config tree
     * answers the same mistake with "Did you mean ...?", and Gacela reads the
     * same helper for a mistyped key in `gacela.php`.
     */
    public function test_a_mistyped_key_is_answered_with_the_one_meant(): void
    {
        $this->expectExceptionMessage("Did you mean?\n  - cache_dir");

        Configuration::validate(['cache_dri' => '/tmp/gacela']);
    }

    /**
     * A key resembling nothing gets the list and no guess: a suggestion that
     * is wrong is worse than none, because it is followed.
     */
    public function test_a_key_resembling_nothing_is_not_guessed_at(): void
    {
        try {
            Configuration::validate(['zzzzzzzzzzzz' => true]);
            self::fail('Expected the validation to fail');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('zzzzzzzzzzzz', $invalidArgumentException->getMessage());
            self::assertStringNotContainsString('Did you mean?', $invalidArgumentException->getMessage());
        }
    }

    public function test_an_unknown_key_fails_naming_the_allowed_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cache_dir');

        Configuration::validate(['cache_dri' => '/tmp/gacela']);
    }

    #[DataProvider('mistypedProvider')]
    public function test_a_mistyped_key_fails_naming_the_key(string $key, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('"%s"', $key));

        Configuration::validate([$key => $value]);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function mistypedProvider(): iterable
    {
        yield 'enabled not bool' => ['enabled', 'yes'];
        yield 'register_commands not bool' => ['register_commands', 1];
        yield 'file_cache not bool nor null' => ['file_cache', 'on'];
        yield 'app_root_dir not string nor null' => ['app_root_dir', 42];
        yield 'cache_dir not string nor null' => ['cache_dir', false];
        yield 'command_prefix not string' => ['command_prefix', null];
        yield 'project_namespaces not a list' => ['project_namespaces', 'App'];
        yield 'project_namespaces with non-string' => ['project_namespaces', [42]];
        yield 'project_namespaces a map' => ['project_namespaces', ['a' => 'App']];
        yield 'external_services not a map' => ['external_services', 'log'];
        yield 'external_services with non-string key' => ['external_services', ['log']];
        yield 'external_services with non-string value' => ['external_services', ['logger' => 42]];
    }
}
