<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\Exception\ResolvableTypeException;

use function array_keys;
use function count;
use function krsort;
use function strlen;

/**
 * Every class kind Gacela resolves by suffix, and the suffixes each answers to.
 *
 * The four pillars used to be written down twice -- once in `ResolvableType` as
 * the types a class name can end in, once in `SuffixTypesBuilder` as the
 * suffixes each accepts -- and the two agreed by hand. A project-declared kind
 * would have had to keep that agreement in a place no test in this repository
 * can see, so `BUILT_IN` is now the one definition and both readers consult it.
 *
 * The declarations themselves arrive from the assembled configuration through
 * {@see syncFrom()}, once per bootstrap. They live in a static because
 * `ResolvableType::fromClassName()` is static and reached from key
 * normalization, where no container is in hand.
 *
 * @internal
 */
final class ResolvableTypes
{
    public const FACADE = 'Facade';

    public const FACTORY = 'Factory';

    public const CONFIG = 'Config';

    public const PROVIDER = 'Provider';

    /** The four kinds that always exist, and the suffix each answers to by default. */
    public const BUILT_IN = [
        self::FACADE => [self::FACADE],
        self::FACTORY => [self::FACTORY],
        self::CONFIG => [self::CONFIG],
        self::PROVIDER => [self::PROVIDER],
    ];

    /** @var array<string, list<string>> kind => suffixes, built-ins included */
    private static array $suffixes = self::BUILT_IN;

    /** @var array<string, string>|null memo of matchOrder(): suffix => kind */
    private static ?array $matchOrder = null;

    /**
     * Declarations are not cache: they are configuration, and the next
     * bootstrap's {@see syncFrom()} replaces them wholesale.
     *
     * Wiping them back to the pillars on a cache reset looked tidy and was
     * expensive: a project with custom suffixes then changed the set twice per
     * bootstrap -- once to the pillars here, once back on sync -- and each
     * change drops the two key memos, so every class name in the application
     * was normalized again on every bootstrap. Letting the sync be the only
     * writer keeps the memos across bootstraps that declare the same thing,
     * which is all of them after the first.
     *
     * @internal
     */
    public static function resetToBuiltIn(): bool
    {
        return self::apply(self::BUILT_IN);
    }

    /**
     * Take the kinds the *merged* configuration declared.
     *
     * Merged, not per-source: a project declares its kinds in `gacela.php`,
     * which is one of several sources assembled and reduced into a single
     * config file, and only that result knows every declaration. Syncing per
     * source made the last one assembled win, which is how a kind declared in
     * `gacela.php` went missing behind a bootstrap closure that declared none.
     *
     * @param array<string, list<string>> $suffixes
     */
    public static function syncFrom(array $suffixes): bool
    {
        return self::apply($suffixes === [] ? self::BUILT_IN : $suffixes);
    }

    /**
     * Refuse a suffix that names more than one kind.
     *
     * The rule lives here, and both places that can see a complete map call
     * it: the builder while one source is being declared, where the error
     * points at the line that wrote it, and the merge of every source, where
     * two sources that were each fine alone first meet. Validating only in the
     * builder let `gacela.php` and a bootstrap closure declare the same suffix
     * for different kinds -- each passing, the union silently naming neither.
     *
     * Pillars sharing a suffix stays legal: the per-kind list generates
     * candidates for a resolver that already knows its kind, and only the
     * reverse mapping degrades. A declared kind has no such history.
     *
     * @param array<string, list<string>> $suffixesByKind
     */
    public static function assertUnambiguous(array $suffixesByKind): void
    {
        $owners = [];
        foreach ($suffixesByKind as $kind => $suffixes) {
            foreach ($suffixes as $suffix) {
                $owners[$suffix][] = $kind;
            }
        }

        foreach ($owners as $suffix => $claimants) {
            if (count($claimants) < 2) {
                continue;
            }

            foreach ($claimants as $claimant) {
                if (!isset(self::BUILT_IN[$claimant])) {
                    throw ResolvableTypeException::suffixAlreadyClaimed($suffix, $claimants[0], $claimant);
                }
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function all(): array
    {
        return self::$suffixes;
    }

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return array_keys(self::$suffixes);
    }

    /**
     * The kinds a project declared, without the four that always exist.
     *
     * @return list<string>
     */
    public static function declaredKinds(): array
    {
        $declared = [];

        foreach (array_keys(self::$suffixes) as $kind) {
            if (!isset(self::BUILT_IN[$kind])) {
                $declared[] = $kind;
            }
        }

        return $declared;
    }

    /**
     * @return list<string>
     */
    public static function suffixesOf(string $kind): array
    {
        return self::$suffixes[$kind] ?? [$kind];
    }

    /**
     * Suffix to the kind it names, longest suffix first.
     *
     * Order is the whole point: a project's `ServiceProvider` must win over the
     * built-in `Provider` it ends with, or the kind is unreachable through a
     * name the project owns.
     *
     * A flat map rather than a list of pairs, because the caller walks it on
     * the resolution path: a suffix names exactly one kind here -- an
     * ambiguous one is dropped below -- so the suffix is the key, and the
     * consumer needs no per-element array to read it out of.
     *
     * @return array<string, string>
     */
    public static function matchOrder(): array
    {
        if (self::$matchOrder !== null) {
            return self::$matchOrder;
        }

        $owners = [];
        foreach (self::$suffixes as $kind => $suffixes) {
            foreach ($suffixes as $suffix) {
                $owners[$suffix][] = $kind;
            }
        }

        $byLength = [];
        foreach (self::$suffixes as $kind => $suffixes) {
            foreach ($suffixes as $suffix) {
                // A suffix two kinds share names neither of them: it cannot say
                // which kind a class ending in it is, so it does not answer at
                // all, and the caller falls back to the last namespace segment.
                if (count($owners[$suffix]) === 1) {
                    $byLength[strlen($suffix)][$suffix] = $kind;
                }
            }
        }

        krsort($byLength);

        $ordered = [];
        foreach ($byLength as $sameLength) {
            foreach ($sameLength as $suffix => $kind) {
                $ordered[$suffix] = $kind;
            }
        }

        return self::$matchOrder = $ordered;
    }

    /**
     * @param array<string, list<string>> $suffixes
     */
    private static function apply(array $suffixes): bool
    {
        // The overwhelmingly common case is a project that declares no kind of
        // its own, where this is called once per bootstrap with a map equal to
        // the one already in force. Returning before touching either static
        // keeps the pillars' array shared with the configuration that produced
        // it and leaves the match order memoized -- writing them anyway cost
        // measurably more than the comparison saves.
        if (self::$suffixes === $suffixes) {
            return false;
        }

        self::$suffixes = $suffixes;
        self::$matchOrder = null;

        return true;

    }
}
