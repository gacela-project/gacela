<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FilenameSanitizer;

use RuntimeException;

use function count;
use function implode;
use function sprintf;
use function str_contains;
use function strlen;
use function strtolower;

/**
 * Which file `make:file` was asked for, from whatever the user typed.
 *
 * The four pillars are always on offer. A project that declared further kinds
 * puts them on offer too, matched by the same rule: `expo` reaches a declared
 * `Exporter` the way `faca` reaches `Facade`. Undeclared, the word is only a
 * string to fuzzy-match, and lands on whichever pillar it always did.
 */
final class FilenameSanitizer implements FilenameSanitizerInterface
{
    public const FACADE = 'Facade';

    public const FACTORY = 'Factory';

    public const CONFIG = 'Config';

    public const PROVIDER = 'Provider';

    public const EXPECTED_FILENAMES = [
        self::FACADE,
        self::FACTORY,
        self::CONFIG,
        self::PROVIDER,
    ];

    /**
     * The pillars, which every project has. `make:module` scaffolds exactly
     * these; a declared kind is generated one file at a time by `make:file`.
     */
    /**
     * Names a kind answers to besides its own, lowercased.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        self::PROVIDER => ['dependency-provider'],
    ];

    /**
     * @param list<string> $declaredKinds the kinds this project declared, beyond the pillars
     */
    public function __construct(
        private readonly array $declaredKinds = [],
    ) {
    }

    /**
     * The expected filenames rendered for console help text.
     *
     * @param list<string> $declaredKinds
     */
    public static function expectedFilenamesAsText(array $declaredKinds = []): string
    {
        return implode(', ', [...self::EXPECTED_FILENAMES, ...$declaredKinds]);
    }

    /**
     * @return non-empty-list<string>
     */
    public function getExpectedFilenames(): array
    {
        return [...self::EXPECTED_FILENAMES, ...$this->declaredKinds];
    }

    public function sanitize(string $filename): string
    {
        $matches = $this->matching($filename);

        if ($matches === []) {
            throw new RuntimeException(sprintf(
                "\"%s\" is not one of the filenames make:file can generate: %s.\n"
                . "Declare it with addResolvableType('%s') in gacela.php to generate it as a kind of its own.",
                $filename,
                implode(', ', $this->getExpectedFilenames()),
                $filename,
            ));
        }

        if (count($matches) > 1) {
            throw new RuntimeException(sprintf(
                'When using "%s", which filename do you mean [%s]?',
                $filename,
                implode(' or ', $matches),
            ));
        }

        return $matches[0];
    }

    /**
     * The kinds the typed word can mean.
     *
     * The letters typed, in order, somewhere in the kind's name -- so `cade`
     * and `tory` reach `Facade` and `Factory`, and `fig` reaches `Config`.
     * A word that spells a kind out in full reaches it too, which is how
     * `dependency-provider` finds `Provider`.
     *
     * Not a similarity score, because no threshold separates the words that
     * should match from the words that should not. `de-pr` scores 36% against
     * `Provider` and `Service` scores 53%, so any cutoff keeping the first
     * keeps the second -- and `Service` silently produced a `Provider` file.
     * Asking whether the letters are there answers every case exactly.
     *
     * @return list<string>
     */
    private function matching(string $filename): array
    {
        $needle = strtolower($filename);
        $matches = [];

        foreach ($this->getExpectedFilenames() as $expected) {
            foreach ($this->namesFor($expected) as $name) {
                if ($this->spellsOut($needle, $name) || str_contains($needle, $name)) {
                    $matches[] = $expected;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Every name a kind answers to, lowercased.
     *
     * `Provider` carries the name it had before it was renamed, so the
     * abbreviations built on it -- `de-pr` for `dependency-provider` -- keep
     * working.
     *
     * @return list<string>
     */
    private function namesFor(string $kind): array
    {
        return [strtolower($kind), ...self::ALIASES[$kind] ?? []];
    }

    /**
     * Whether every character of the needle appears in the haystack, in order.
     */
    private function spellsOut(string $needle, string $haystack): bool
    {
        $at = 0;
        $length = strlen($needle);

        for ($i = 0, $end = strlen($haystack); $i < $end && $at < $length; ++$i) {
            if ($haystack[$i] === $needle[$at]) {
                ++$at;
            }
        }

        return $at === $length;
    }
}
