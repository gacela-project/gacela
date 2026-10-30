<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Framework\Exception\AliasLoopException;

use function array_keys;

final readonly class AliasMap
{
    /**
     * @param array<string, string> $aliases
     */
    public function __construct(private array $aliases)
    {
    }

    /**
     * @return array<string, string>
     */
    public function resolved(): array
    {
        $resolved = [];
        foreach (array_keys($this->aliases) as $alias) {
            $resolved[$alias] = $this->targetOf($alias);
        }

        return $resolved;
    }

    private function targetOf(string $alias): string
    {
        $seen = [];
        while (isset($this->aliases[$alias])) {
            if (isset($seen[$alias])) {
                throw AliasLoopException::detected([...array_keys($seen), $alias]);
            }

            $seen[$alias] = true;
            $alias = $this->aliases[$alias];
        }

        return $alias;
    }
}
