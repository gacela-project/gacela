<?php

declare(strict_types=1);

namespace Gacela\LaravelBridge;

use Gacela\Framework\Exception\ErrorSuggestionHelper;
use InvalidArgumentException;

use function array_diff_key;
use function array_is_list;
use function array_keys;
use function gettype;
use function implode;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;

/**
 * What `config/gacela.php` may say.
 *
 * The bundle validates with a TreeBuilder at compile time; Laravel has no
 * compile step, so the same philosophy runs at boot: an unknown or mistyped
 * key fails there, where it is a five-second fix, instead of at the first use
 * of whatever it was supposed to configure.
 *
 * @psalm-type GacelaBridgeConfig = array{
 *     enabled: bool,
 *     app_root_dir: ?string,
 *     cache_dir: ?string,
 *     file_cache: ?bool,
 *     project_namespaces: list<string>,
 *     external_services: array<string, string>,
 *     register_commands: bool,
 *     command_prefix: string
 * }
 */
final class Configuration
{
    public const DEFAULTS_FILE = __DIR__ . '/../config/gacela.php';

    /**
     * @param array<array-key, mixed> $config
     *
     * @return GacelaBridgeConfig
     */
    public static function validate(array $config): array
    {
        // The literal path, not DEFAULTS_FILE: an include behind a constant is
        // one psalm cannot resolve to a file.
        /** @var GacelaBridgeConfig $defaults */
        $defaults = require __DIR__ . '/../config/gacela.php';

        $unknown = array_diff_key($config, $defaults);
        if ($unknown !== []) {
            $unknownKeys = array_keys($unknown);
            $allowedKeys = array_keys($defaults);

            // The suggestion on top of the list, not instead of it. Symfony's
            // own config tree answers the same mistake with "Did you mean
            // ...?", and Gacela already reads this helper for a mistyped
            // config key -- a typo in `config/gacela.php` is the same mistake
            // in a different file.
            throw new InvalidArgumentException(sprintf(
                'Unknown gacela config key(s): "%s". Allowed keys: "%s".%s',
                implode('", "', $unknownKeys),
                implode('", "', $allowedKeys),
                ErrorSuggestionHelper::suggestSimilar((string)$unknownKeys[0], $allowedKeys),
            ));
        }

        $config += $defaults;

        self::assertBool($config, 'enabled');
        self::assertBool($config, 'register_commands');
        self::assertNullableBool($config, 'file_cache');
        self::assertNullableString($config, 'app_root_dir');
        self::assertNullableString($config, 'cache_dir');
        self::assertString($config, 'command_prefix');
        self::assertListOfStrings($config, 'project_namespaces');
        self::assertMapOfStrings($config, 'external_services');

        /** @var GacelaBridgeConfig $config */
        return $config;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function assertBool(array $config, string $key): void
    {
        if (!is_bool($config[$key])) {
            throw self::mistyped($key, 'a boolean', $config[$key]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function assertNullableBool(array $config, string $key): void
    {
        if ($config[$key] !== null && !is_bool($config[$key])) {
            throw self::mistyped($key, 'a boolean or null', $config[$key]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function assertString(array $config, string $key): void
    {
        if (!is_string($config[$key])) {
            throw self::mistyped($key, 'a string', $config[$key]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function assertNullableString(array $config, string $key): void
    {
        if ($config[$key] !== null && !is_string($config[$key])) {
            throw self::mistyped($key, 'a string or null', $config[$key]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function assertListOfStrings(array $config, string $key): void
    {
        $value = $config[$key];
        if (!is_array($value) || !array_is_list($value)) {
            throw self::mistyped($key, 'a list of strings', $value);
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw self::mistyped($key, 'a list of strings', $item);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function assertMapOfStrings(array $config, string $key): void
    {
        $value = $config[$key];
        if (!is_array($value)) {
            throw self::mistyped($key, 'a map of string keys to string values', $value);
        }

        foreach ($value as $mapKey => $item) {
            if (!is_string($mapKey) || !is_string($item)) {
                throw self::mistyped($key, 'a map of string keys to string values', $item);
            }
        }
    }

    private static function mistyped(string $key, string $expected, mixed $actual): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'The gacela config key "%s" must be %s, got %s.',
            $key,
            $expected,
            gettype($actual),
        ));
    }
}
