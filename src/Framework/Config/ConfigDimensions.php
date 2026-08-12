<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Exception\ConfigDimensionException;

use function getenv;
use function is_string;
use function preg_match;

/**
 * The values selecting this configuration, beyond the environment.
 *
 * `APP_ENV` answers *which environment*; a dimension answers a further
 * question the same way -- which region, which tenant, which brand. A project
 * declares the variables it wants consulted, in order, and the resolved values
 * form a tuple that selects config files and names the merged-config cache.
 *
 * @internal
 */
final class ConfigDimensions
{
    /**
     * Reaches a glob pattern and a filename, so the alphabet is bounded. Wide
     * enough that no plausible existing `APP_ENV` stops working, narrow enough
     * that `/` and `..` cannot walk out of the config directory.
     */
    private const VALUE_PATTERN = '/^[A-Za-z0-9_.-]*$/';

    /**
     * @param list<string> $values
     */
    private function __construct(
        private readonly array $values,
    ) {
    }

    /**
     * Read the declared variables, in order, stopping at the first unset one.
     *
     * Stopping matters: with region unset, a tenant value would otherwise
     * produce `app-prod--acme.php` and a tuple with a hole in it, and no
     * answer to what that file means. Terminating keeps the chain a strict
     * prefix of itself, which is also what keeps `config/*-prod.php` from
     * matching `app-prod-eu.php`.
     *
     * @param list<string> $declaredVariables
     */
    public static function fromEnvironment(array $declaredVariables): self
    {
        $values = [];

        foreach ($declaredVariables as $variable) {
            $value = getenv($variable);

            if (!is_string($value) || $value === '') {
                break;
            }

            if (preg_match(self::VALUE_PATTERN, $value) !== 1) {
                throw ConfigDimensionException::invalidValue($variable, $value);
            }

            $values[] = $value;
        }

        return new self($values);
    }

    /**
     * @param list<string> $values
     */
    public static function fromValues(array $values): self
    {
        return new self($values);
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * The suffixes a config file may carry, most general first.
     *
     * Cumulative rather than independent: `app-prod-eu.php` refines
     * `app-prod.php`, which refines `app.php`. Independent passes would need a
     * precedence lattice -- does `app-eu.php` beat `app-prod.php`? -- with no
     * natural answer and a rule the docs would carry forever. A chain has one
     * sentence: more specific wins.
     *
     * @param string $env the environment, which is the first link of the chain
     *
     * @return list<string>
     */
    public function suffixChain(string $env): array
    {
        if ($env === '') {
            return [];
        }

        $chain = [$env];
        $current = $env;

        foreach ($this->values as $value) {
            $current .= '-' . $value;
            $chain[] = $current;
        }

        return $chain;
    }
}
