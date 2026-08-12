<?php

declare(strict_types=1);

namespace Gacela\Framework\Dto\Schema;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function ksort;
use function preg_match;

/**
 * Every shape an application declares, by the class each generates.
 *
 * Checked where it is declared rather than where it is read: a shape that
 * cannot generate a class is a mistake in `gacela.php`, and reporting it at
 * generation time would name a file nobody wrote.
 */
final class DtoSchema
{
    private const CLASS_NAME_PATTERN = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)+$/';

    private const PROPERTY_NAME_PATTERN = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/';

    /**
     * @param array<class-string, array<string, DtoType>> $shapes
     */
    private function __construct(
        private readonly array $shapes,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<string, mixed> $shapes class name => (property => DtoType)
     *
     * @throws MalformedDtoSchemaException
     */
    public static function fromArray(array $shapes): self
    {
        $checked = [];

        /** @var mixed $properties */
        foreach ($shapes as $className => $properties) {
            if (preg_match(self::CLASS_NAME_PATTERN, $className) !== 1) {
                throw MalformedDtoSchemaException::notAValidClassName($className);
            }

            /** @var class-string $className */
            $checked[$className] = self::checkedProperties($className, (array)$properties);
        }

        // Sorted so the generated output does not depend on which config source
        // happened to load first.
        ksort($checked);

        return new self($checked);
    }

    public function isEmpty(): bool
    {
        return $this->shapes === [];
    }

    /**
     * @return array<class-string, array<string, DtoType>>
     */
    public function shapes(): array
    {
        return $this->shapes;
    }

    public function declares(string $className): bool
    {
        return array_key_exists($className, $this->shapes);
    }

    /**
     * @param array<array-key, mixed> $properties
     *
     * @return array<string, DtoType>
     */
    private static function checkedProperties(string $className, array $properties): array
    {
        $checked = [];

        /** @var mixed $type */
        foreach ($properties as $property => $type) {
            if (!is_string($property) || preg_match(self::PROPERTY_NAME_PATTERN, $property) !== 1) {
                throw MalformedDtoSchemaException::notAValidPropertyName($className, (string)$property);
            }

            if (!$type instanceof DtoType) {
                throw MalformedDtoSchemaException::notAType($className, $property);
            }

            self::assertDeclarationIsCoherent($className, $property, $type);
            $checked[$property] = $type;
        }

        ksort($checked);

        return $checked;
    }

    private static function assertDeclarationIsCoherent(string $className, string $property, DtoType $type): void
    {
        if ($type->isRequired && $type->hasDefault) {
            throw MalformedDtoSchemaException::requiredWithDefault($className, $property);
        }

        if ($type->hasDefault && !self::defaultMatches($type)) {
            throw MalformedDtoSchemaException::defaultOfWrongType($className, $property, $type, $type->default);
        }
    }

    private static function defaultMatches(DtoType $type): bool
    {
        return match ($type->name) {
            DtoType::STRING => is_string($type->default),
            DtoType::INT => is_int($type->default),
            // An int is a legitimate float default: `0` is about the value, not
            // about php's literal syntax.
            DtoType::FLOAT => is_float($type->default) || is_int($type->default),
            DtoType::BOOL => is_bool($type->default),
            default => is_array($type->default),
        };
    }
}
