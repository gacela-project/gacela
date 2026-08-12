<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FilenameSanitizer;

use RuntimeException;

use function count;
use function sprintf;

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

    /**
     * The pillars, which every project has. `make:module` scaffolds exactly
     * these; a declared kind is generated one file at a time by `make:file`.
     */
    public const EXPECTED_FILENAMES = [
        self::FACADE,
        self::FACTORY,
        self::CONFIG,
        self::PROVIDER,
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
        $percents = [];

        foreach ($this->getExpectedFilenames() as $expected) {
            $percents[$expected] = similar_text($expected, $filename);
        }

        $maxVal = max($percents);
        $maxValKeys = array_keys($percents, $maxVal, true);

        if (count($maxValKeys) > 1) {
            throw new RuntimeException(sprintf(
                'When using "%s", which filename do you mean [%s]?',
                $filename,
                implode(' or ', $maxValKeys),
            ));
        }

        /** @psalm-suppress RedundantCast */
        return (string)reset($maxValKeys);
    }
}
