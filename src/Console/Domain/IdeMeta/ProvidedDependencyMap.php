<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\IdeMeta;

/**
 * What `getProvidedDependency()` answers for each string id an application
 * registers -- and which ids have no single answer.
 */
final class ProvidedDependencyMap
{
    /**
     * @param array<string, class-string> $entries id => the class it resolves to
     * @param array<string, list<class-string>> $ambiguous id => every class claiming it
     */
    public function __construct(
        private readonly array $entries = [],
        private readonly array $ambiguous = [],
    ) {
    }

    /**
     * @return array<string, class-string>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Ids two providers register with different types.
     *
     * They are reported rather than emitted because the editor map is keyed on
     * the argument value across the whole application, while
     * `getProvidedDependency()` reads the calling module's own container. One
     * entry would therefore be right in one module and wrong in the other, and
     * a wrong type is worse than an absent one: it type-checks a call that
     * fails.
     *
     * @return array<string, list<class-string>>
     */
    public function ambiguous(): array
    {
        return $this->ambiguous;
    }
}
