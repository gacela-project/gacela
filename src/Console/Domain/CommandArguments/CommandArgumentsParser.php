<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\CommandArguments;

use Gacela\Console\ConsoleConfig;
use InvalidArgumentException;

use function count;
use function is_string;

/**
 * @psalm-import-type ComposerJsonContent from ConsoleConfig
 */
final class CommandArgumentsParser implements CommandArgumentsParserInterface
{
    /**
     * @param ComposerJsonContent $composerJson
     */
    public function __construct(
        private array $composerJson,
    ) {
    }

    /**
     * @param string $desiredNamespace The location of the new module. For example: App/TestModule
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $desiredNamespace): CommandArguments
    {
        if (!isset($this->composerJson['autoload'])) {
            throw CommandArgumentsException::noAutoloadFound();
        }

        if (!isset($this->composerJson['autoload']['psr-4'])) {
            throw CommandArgumentsException::noAutoloadPsr4Found();
        }

        $psr4 = $this->composerJson['autoload']['psr-4'];
        $psr4Dev = $this->composerJson['autoload-dev']['psr-4'] ?? [];

        // `+` keeps the left operand on a duplicate key, which is the same
        // precedence the two separate isset() chains encoded: autoload wins
        // over autoload-dev for a namespace declared in both.
        $psr4All = $psr4 + $psr4Dev;

        foreach ($this->allPossiblePsr4Combinations($desiredNamespace) as $psr4Combination) {
            $psr4Key = $psr4Combination . '\\';

            if (isset($psr4All[$psr4Key])) {
                return $this->foundPsr4($psr4Key, $psr4All[$psr4Key], $desiredNamespace);
            }
        }

        throw CommandArgumentsException::noAutoloadPsr4MatchFound($desiredNamespace, array_keys($psr4));
    }

    /**
     * Every psr-4 prefix the requested namespace could match, longest first, so
     * the most specific mapping wins.
     *
     * The input is slash-separated and the output is backslash-separated,
     * because these are compared against psr-4 keys: `App/TestModule/TestSubModule`
     * produces
     * [
     *   'App\TestModule\TestSubModule',
     *   'App\TestModule',
     *   'App',
     * ]
     *
     * @return list<string>
     */
    private function allPossiblePsr4Combinations(string $desiredNamespace): array
    {
        $result = [];

        foreach (explode('/', $desiredNamespace) as $explodedArg) {
            if ($result === []) {
                $result[] = $explodedArg;
            } else {
                $prevValue = $result[count($result) - 1];
                $result[] = $prevValue . '\\' . $explodedArg;
            }
        }

        return array_reverse($result);
    }

    /**
     * @param string|list<string> $psr4Value
     */
    private function foundPsr4(string $psr4Key, string|array $psr4Value, string $desiredNamespace): CommandArguments
    {
        $rootNamespace = rtrim($psr4Key, '\\');
        $rootDir = rtrim($this->firstDirectoryOf($psr4Value), '/');

        // Only the matched prefix is removed. Replacing the namespace text
        // globally also rewrote it where it recurs inside the module name, so
        // `App/Application` against `App\ => src/` produced `src/srclication`.
        $prefixAsPath = str_replace('\\', '/', $rootNamespace);
        $remainder = trim(mb_substr($desiredNamespace, mb_strlen($prefixAsPath)), '/');

        return new CommandArguments(
            $this->join('\\', $rootNamespace, str_replace('/', '\\', $remainder)),
            $this->join('/', $rootDir, $remainder),
        );
    }

    /**
     * Composer allows a psr-4 target to be a single directory or a list of
     * them. Only the first is used: it is where Composer itself looks first,
     * and generating into any of the others would be a guess.
     *
     * @param string|list<string> $psr4Value
     */
    private function firstDirectoryOf(string|array $psr4Value): string
    {
        if (is_string($psr4Value)) {
            return $psr4Value;
        }

        return $psr4Value[0] ?? '';
    }

    /**
     * Joins the parts that are present, so a namespace root with nothing after
     * it does not pick up a dangling separator.
     */
    private function join(string $separator, string ...$parts): string
    {
        return implode($separator, array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
