<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigBuilder;

use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\Exception\ResolvableTypeException;

use function array_unique;
use function array_values;
use function class_exists;
use function in_array;
use function interface_exists;

/**
 * The kinds this project resolves by suffix.
 *
 * The four pillars are seeded from {@see ResolvableTypes::BUILT_IN} rather than
 * spelled out again, so there is one definition of what they are. A project
 * declares further kinds with {@see declareType()}, and from then on they are
 * ordinary keys in the same map.
 *
 * @psalm-type SuffixTypes = array<string, list<string>>
 */
final class SuffixTypesBuilder
{
    public const DEFAULT_SUFFIX_TYPES = ResolvableTypes::BUILT_IN;

    /** @var array<string, list<string>> */
    private array $suffixTypes = self::DEFAULT_SUFFIX_TYPES;

    public function addFacade(string $suffix): self
    {
        return $this->addType(ResolvableTypes::FACADE, $suffix);
    }

    public function addFactory(string $suffix): self
    {
        return $this->addType(ResolvableTypes::FACTORY, $suffix);
    }

    public function addConfig(string $suffix): self
    {
        return $this->addType(ResolvableTypes::CONFIG, $suffix);
    }

    public function addProvider(string $suffix): self
    {
        return $this->addType(ResolvableTypes::PROVIDER, $suffix);
    }

    /**
     * Teach one kind a further suffix. The kind may be a pillar or a declared one.
     */
    public function addType(string $kind, string $suffix): self
    {
        return $this->declareType($kind, null, [$suffix]);
    }

    /**
     * Declare a kind, or widen one that already exists.
     *
     * Re-declaring is how `addSuffixTypeFacade()` works, so a repeated kind
     * merges its suffixes rather than failing. A suffix already claimed by
     * *another* kind does fail, here at bootstrap rather than at the first
     * resolution that goes the wrong way: which kind won would otherwise depend
     * on declaration order, which is not something a project can reason about.
     *
     * The abstract is checked and not kept: a kind whose base is a typo is
     * worth refusing at bootstrap, and nothing in this part reads the class
     * back. The pair that does read it (a Reader and a Writer resolved by
     * suffix) carries it when it ships.
     *
     * @param class-string|null $abstractClass
     * @param list<string> $suffixes
     */
    public function declareType(string $kind, ?string $abstractClass = null, array $suffixes = []): self
    {
        if ($kind === '') {
            throw ResolvableTypeException::emptyKind();
        }

        if ($abstractClass !== null && !class_exists($abstractClass) && !interface_exists($abstractClass)) {
            throw ResolvableTypeException::unknownAbstractClass($kind, $abstractClass);
        }

        $suffixes = $suffixes === [] ? [$kind] : $suffixes;

        foreach ($suffixes as $suffix) {
            $this->assertSuffixIsFree($kind, $suffix);
        }

        $this->suffixTypes[$kind] = array_values(array_unique([
            ...$this->suffixTypes[$kind] ?? [],
            ...$suffixes,
        ]));

        return $this;
    }

    /**
     * `declareType()` is the only way in, and it stores a deduplicated list,
     * so there is nothing left to normalize here.
     *
     * @return SuffixTypes
     */
    public function build(): array
    {
        return $this->suffixTypes;
    }

    /**
     * A suffix two kinds share cannot answer "which kind is this name?", and
     * {@see ResolvableTypes::matchOrder()} drops it for exactly that reason.
     *
     * Sharing one between *pillars* stays legal, because it predates this and
     * means something there: the per-kind list generates candidates for a
     * resolver that already knows which kind it wants, so `FooExtra` being
     * both a Facade and a Factory suffix costs nothing. A declared kind has no
     * such history, and a project that gives one an ambiguous suffix has
     * written a name nothing can resolve back -- so it is refused here, at
     * bootstrap, rather than at the first lookup that goes the wrong way.
     */
    private function assertSuffixIsFree(string $kind, string $suffix): void
    {
        foreach ($this->suffixTypes as $otherKind => $otherSuffixes) {
            if ($otherKind === $kind) {
                continue;
            }

            if (!in_array($suffix, $otherSuffixes, true)) {
                continue;
            }

            if (!isset(ResolvableTypes::BUILT_IN[$kind]) || !isset(ResolvableTypes::BUILT_IN[$otherKind])) {
                throw ResolvableTypeException::suffixAlreadyClaimed($suffix, $otherKind, $kind);
            }
        }
    }
}
