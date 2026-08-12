<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\Exception\ResolvableTypeException;

use function array_keys;
use function count;
use function strlen;
use function usort;

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

    /** @var list<array{kind: string, suffix: string}>|null memo of matchOrder() */
    private static ?array $matchOrder = null;

    /**
     * @internal
     *
     * @return bool whether the declared set actually changed
     */
    public static function resetCache(): bool
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
     * Every (kind, suffix) pair, longest suffix first.
     *
     * Order is the whole point: a project's `ServiceProvider` must win over the
     * built-in `Provider` it ends with, or the kind is unreachable through a
     * name the project owns.
     *
     * @return list<array{kind: string, suffix: string}>
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

        $pairs = [];
        foreach (self::$suffixes as $kind => $suffixes) {
            foreach ($suffixes as $suffix) {
                // A suffix two kinds share names neither of them: it cannot say
                // which kind a class ending in it is, so it does not answer at
                // all, and the caller falls back to the last namespace segment.
                if (count($owners[$suffix]) === 1) {
                    $pairs[] = ['kind' => $kind, 'suffix' => $suffix];
                }
            }
        }

        usort(
            $pairs,
            /**
             * @param array{kind: string, suffix: string} $a
             * @param array{kind: string, suffix: string} $b
             */
            static fn (array $a, array $b): int => strlen($b['suffix']) <=> strlen($a['suffix']),
        );

        return self::$matchOrder = $pairs;
    }

    /**
     * @param array<string, list<string>> $suffixes
     */
    private static function apply(array $suffixes): bool
    {
        $changed = self::$suffixes !== $suffixes;

        self::$suffixes = $suffixes;
        self::$matchOrder = null;

        return $changed;

    }
}
