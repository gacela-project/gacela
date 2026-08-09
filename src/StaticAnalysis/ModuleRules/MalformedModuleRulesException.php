<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\ModuleRules;

use RuntimeException;

use function sprintf;

final class MalformedModuleRulesException extends RuntimeException
{
    public static function missingRulesList(): self
    {
        return new self('A module rules file must be an object with a "rules" list.');
    }

    public static function entryIsNotAnObject(int $position): self
    {
        return new self(sprintf(
            'Module rule #%d must be an object with "from", either "allow" or "deny", and a "reason".',
            $position,
        ));
    }

    public static function missingFrom(int $position): self
    {
        return new self(sprintf('Module rule #%d needs a non-empty "from" namespace.', $position));
    }

    public static function missingDirection(int $position): self
    {
        return new self(sprintf(
            'Module rule #%d needs either "allow" or "deny": a rule that forbids nothing is not a rule.',
            $position,
        ));
    }

    public static function bothDirections(int $position): self
    {
        return new self(sprintf(
            'Module rule #%d has both "allow" and "deny". Pick one: "deny" forbids the listed modules, "allow" forbids every other.',
            $position,
        ));
    }

    public static function emptyDeny(int $position): self
    {
        return new self(sprintf('Module rule #%d denies nothing. Remove it, or list what it forbids.', $position));
    }

    public static function nonStringNamespace(int $position): self
    {
        return new self(sprintf('Module rule #%d lists a namespace that is not a string.', $position));
    }

    public static function missingReason(int $position): self
    {
        return new self(sprintf(
            'Module rule #%d needs a non-empty "reason": a boundary nobody justified is one nobody can revisit.',
            $position,
        ));
    }

    public static function unreadableFile(string $path): self
    {
        return new self(sprintf('Cannot read the module rules file: "%s".', $path));
    }

    public static function invalidJson(string $path, string $error): self
    {
        return new self(sprintf('"%s" is not valid JSON: %s', $path, $error));
    }
}
