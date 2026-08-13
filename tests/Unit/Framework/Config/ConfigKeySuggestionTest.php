<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Exception\ConfigException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A mistyped config key answered with the key that was meant.
 *
 * `ConfigException::keyNotFound()` has always accepted the available keys and
 * always been handed none, so `Did you mean?` was unreachable for config while
 * the identical helper worked for services through {@see \Gacela\Framework\Container\Locator}.
 * Nothing failed -- the suggestion simply never appeared.
 */
final class ConfigKeySuggestionTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
            $config->addAppConfigKeyValues([
                'database' => ['host' => 'localhost'],
                'api-token' => 'super-secret-value',
                'retries' => 3,
            ]);
        });
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    /**
     * Every typed getter raises this independently, and the suggestion was
     * missing from all six. Asked of each one so a getter added later cannot
     * quietly go back to the bare message.
     *
     * @return iterable<string, array{callable(Config, string): mixed}>
     */
    public static function getters(): iterable
    {
        yield 'get' => [static fn (Config $c, string $k): mixed => $c->get($k)];
        yield 'getString' => [static fn (Config $c, string $k): string => $c->getString($k)];
        yield 'getInt' => [static fn (Config $c, string $k): int => $c->getInt($k)];
        yield 'getFloat' => [static fn (Config $c, string $k): float => $c->getFloat($k)];
        yield 'getBool' => [static fn (Config $c, string $k): bool => $c->getBool($k)];
        yield 'getArray' => [static fn (Config $c, string $k): array => $c->getArray($k)];
    }

    /**
     * @param callable(Config, string): mixed $read
     */
    #[DataProvider('getters')]
    public function test_every_getter_suggests_the_key_that_was_meant(callable $read): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("Did you mean?\n  - database");

        $read(Config::getInstance(), 'databse');
    }

    /**
     * Gacela's config is flat -- `hasKey()` is a plain `array_key_exists`, with
     * no traversal into nested values. Reaching for `database.host` is a
     * reasonable habit from elsewhere, and naming `database` is how that reader
     * finds out the nesting is theirs to walk.
     */
    public function test_a_dotted_key_points_at_the_top_level_key_that_holds_it(): void
    {
        $this->expectExceptionMessage("Did you mean?\n  - database");

        Config::getInstance()->get('database.host');
    }

    /**
     * Suggestions are similar keys, not a listing. A key resembling nothing
     * gets the plain message rather than the whole config surface.
     */
    public function test_a_key_resembling_nothing_is_not_offered_alternatives(): void
    {
        try {
            Config::getInstance()->get('zzzzzzzzzz');
            self::fail('Expected ConfigException');
        } catch (ConfigException $configException) {
            self::assertStringContainsString('Could not find config key "zzzzzzzzzz"', $configException->getMessage());
            self::assertStringNotContainsString('Did you mean?', $configException->getMessage());
        }
    }

    /**
     * Key names travel; values do not. This message reaches logs and error
     * pages, and a config holds credentials -- so the suggestion is built from
     * `array_keys()` alone.
     */
    public function test_the_suggestion_never_carries_a_config_value(): void
    {
        try {
            Config::getInstance()->getString('api-toke');
            self::fail('Expected ConfigException');
        } catch (ConfigException $configException) {
            self::assertStringContainsString('api-token', $configException->getMessage());
            self::assertStringNotContainsString('super-secret-value', $configException->getMessage());
        }
    }

    /**
     * A default is the caller saying the key is optional, so a miss is not a
     * mistake to be corrected.
     */
    public function test_a_getter_given_a_default_still_returns_it(): void
    {
        self::assertSame('fallback', Config::getInstance()->getString('databse', 'fallback'));
    }
}
