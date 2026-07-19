<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function sprintf;

final class ConfigException extends RuntimeException
{
    /**
     * @param list<string> $availableKeys
     */
    public static function keyNotFound(string $key, string $class, array $availableKeys = []): self
    {
        $message = sprintf('Could not find config key "%s" in "%s"', $key, $class);
        $message .= ErrorSuggestionHelper::suggestSimilar($key, $availableKeys);
        $message .= ErrorSuggestionHelper::addHelpfulTip('config_error');

        return new self($message);
    }

    public static function invalidType(string $key, string $expectedType, string $actualType): self
    {
        return new self(sprintf(
            'Config key "%s" expected "%s", got "%s". Values are not coerced; fix the config value or use get() for a raw value.',
            $key,
            $expectedType,
            $actualType,
        ));
    }
}
