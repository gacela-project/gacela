<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\ModuleRules;

use JsonException;

use function array_key_exists;
use function array_values;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * The dependencies between modules somebody decided on, in a form both the CLI
 * and the analysers can read.
 *
 * A cycle is the only thing the module graph could refuse on its own. Everything
 * else a team agrees on -- billing must not reach back-office, reporting reads
 * and nothing more -- lived in prose, where the tooling cannot see it and a
 * violation is one more import in a diff. This is that agreement, machine-read.
 *
 * The same file feeds `debug:graph --check`, which sees whole modules, the
 * PHPStan/Psalm rules, which see one class at a time, and
 * {@see \Gacela\Console\Testing\ModuleAssertions}, which reads it from a test
 * method. One decision, however you happen to be looking: a rule that held in
 * CI and not in the editor would be a rule nobody trusts.
 */
final class ModuleRuleSet
{
    /**
     * @param list<ModuleRule> $rules
     */
    private function __construct(
        private readonly array $rules,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @throws MalformedModuleRulesException
     */
    public static function fromFile(string $path): self
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw MalformedModuleRulesException::unreadableFile($path);
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw MalformedModuleRulesException::invalidJson($path, $jsonException->getMessage());
        }

        if (!is_array($decoded)) {
            throw MalformedModuleRulesException::missingRulesList();
        }

        return self::fromDecodedJson($decoded);
    }

    /**
     * @param array<mixed> $decoded
     *
     * @throws MalformedModuleRulesException
     */
    public static function fromDecodedJson(array $decoded): self
    {
        $entries = $decoded['rules'] ?? null;
        if (!is_array($entries)) {
            throw MalformedModuleRulesException::missingRulesList();
        }

        $rules = [];
        $position = 0;

        /** @var mixed $entry */
        foreach ($entries as $entry) {
            $rules[] = self::ruleFrom($entry, $position);
            ++$position;
        }

        return new self($rules);
    }

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * The first rule that governs `$fromModule` and forbids `$toModule`, as the
     * finding it justifies -- null when the dependency is one nobody ruled on.
     */
    public function violationFor(string $fromModule, string $toModule): ?ModuleRuleViolation
    {
        foreach ($this->rules as $rule) {
            if ($rule->governs($fromModule) && $rule->forbids($toModule)) {
                return new ModuleRuleViolation($fromModule, $toModule, $rule->reason);
            }
        }

        return null;
    }

    /**
     * Every namespace the file names, in the order it names them.
     *
     * This is what makes the rules self-invalidating: a namespace that matches
     * no module is a rule that stopped governing anything, and whoever checks
     * the rules against a real module list can say so.
     *
     * @return list<string>
     */
    public function declaredNamespaces(): array
    {
        $namespaces = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->namespaces() as $namespace) {
                $namespaces[$namespace] = $namespace;
            }
        }

        return array_values($namespaces);
    }

    private static function ruleFrom(mixed $entry, int $position): ModuleRule
    {
        if (!is_array($entry)) {
            throw MalformedModuleRulesException::entryIsNotAnObject($position);
        }

        $from = $entry['from'] ?? null;
        if (!is_string($from) || $from === '') {
            throw MalformedModuleRulesException::missingFrom($position);
        }

        $hasAllow = array_key_exists('allow', $entry);
        $hasDeny = array_key_exists('deny', $entry);
        if ($hasAllow && $hasDeny) {
            throw MalformedModuleRulesException::bothDirections($position);
        }

        if (!$hasAllow && !$hasDeny) {
            throw MalformedModuleRulesException::missingDirection($position);
        }

        $reason = $entry['reason'] ?? null;
        if (!is_string($reason) || $reason === '') {
            throw MalformedModuleRulesException::missingReason($position);
        }

        if ($hasDeny) {
            $deny = self::namespaceList($entry['deny'], $position);
            if ($deny === []) {
                throw MalformedModuleRulesException::emptyDeny($position);
            }

            return ModuleRule::deny($from, $deny, $reason);
        }

        return ModuleRule::allow($from, self::namespaceList($entry['allow'], $position), $reason);
    }

    /**
     * @return list<string>
     */
    private static function namespaceList(mixed $value, int $position): array
    {
        if (!is_array($value)) {
            throw MalformedModuleRulesException::missingDirection($position);
        }

        $namespaces = [];

        /** @var mixed $item */
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw MalformedModuleRulesException::nonStringNamespace($position);
            }

            $namespaces[] = $item;
        }

        return $namespaces;
    }
}
