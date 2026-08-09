<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\Schema;

use function array_key_exists;
use function array_keys;

/**
 * What the application's configuration is expected to contain.
 *
 * `validate:config` checks the wiring -- bindings, cycles -- and nothing checked
 * the configuration itself, so a missing or misspelled key surfaced as a runtime
 * failure in whichever environment lacked it: usually production, usually far
 * from the file that should have carried it.
 *
 * Declaring it once turns that into something three existing commands can ask
 * about before anything runs, and it is not on the hot path: bootstrap only
 * takes the defaults, and the checking is opt-in.
 */
final class ConfigSchema
{
    /**
     * @param array<string, ConfigType> $types
     */
    private function __construct(
        private readonly array $types,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<string, mixed> $schema key => ConfigType
     *
     * @throws MalformedConfigSchemaException
     */
    public static function fromArray(array $schema): self
    {
        $types = [];

        /** @var mixed $type */
        foreach ($schema as $key => $type) {
            if (!$type instanceof ConfigType) {
                throw MalformedConfigSchemaException::notAType($key);
            }

            self::assertDeclarationIsCoherent($key, $type);
            $types[$key] = $type;
        }

        return new self($types);
    }

    public function isEmpty(): bool
    {
        return $this->types === [];
    }

    public function declares(string $key): bool
    {
        return array_key_exists($key, $this->types);
    }

    /**
     * @return list<string>
     */
    public function declaredKeys(): array
    {
        return array_keys($this->types);
    }

    /**
     * Every key that does not match its declaration, in the order declared.
     *
     * @param array<string, mixed> $values the merged configuration, as an
     *                                     environment would see it
     *
     * @return list<ConfigSchemaViolation>
     */
    public function violations(array $values): array
    {
        $violations = [];

        foreach ($this->types as $key => $type) {
            if (!array_key_exists($key, $values)) {
                if ($type->isRequired) {
                    $violations[] = ConfigSchemaViolation::missing($key, $type);
                }

                continue;
            }

            if (!$type->accepts($values[$key])) {
                $violations[] = ConfigSchemaViolation::wrongType($key, $type, $values[$key]);
            }
        }

        return $violations;
    }

    /**
     * The declared defaults, for the keys that carry one.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = [];

        foreach ($this->types as $key => $type) {
            if ($type->hasDefault) {
                /** @psalm-suppress MixedAssignment a declared default is whatever the key is declared to be */
                $defaults[$key] = $type->default;
            }
        }

        return $defaults;
    }

    /**
     * @throws MalformedConfigSchemaException
     */
    private static function assertDeclarationIsCoherent(string $key, ConfigType $type): void
    {
        if (!$type->hasDefault) {
            return;
        }

        if ($type->isRequired) {
            throw MalformedConfigSchemaException::requiredWithDefault($key);
        }

        if (!$type->accepts($type->default)) {
            throw MalformedConfigSchemaException::defaultOfWrongType($key, $type, $type->default);
        }
    }
}
