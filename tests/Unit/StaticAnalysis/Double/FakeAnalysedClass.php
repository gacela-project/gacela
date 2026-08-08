<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Double;

use Gacela\StaticAnalysis\AnalysedClassInterface;

use function array_key_exists;
use function array_keys;
use function in_array;

/**
 * The seam, answered from plain arrays.
 *
 * The analysers are the only place the pillar rules are decided, so their tests
 * should be able to state a hierarchy outright rather than build one a host
 * analyser then has to be run to read back.
 */
final class FakeAnalysedClass implements AnalysedClassInterface
{
    /**
     * @param list<string>              $parents    fully qualified, nearest first
     * @param array<string, list<string>> $interfaces fully qualified name => its method names
     */
    public function __construct(
        private readonly string $name,
        private readonly array $parents = [],
        private readonly array $interfaces = [],
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function extendsClass(string $parent): bool
    {
        return in_array($parent, $this->parents, true);
    }

    public function interfaceNames(): array
    {
        return array_keys($this->interfaces);
    }

    public function interfaceHasMethod(string $interface, string $method): bool
    {
        if (!array_key_exists($interface, $this->interfaces)) {
            return false;
        }

        return in_array($method, $this->interfaces[$interface], true);
    }
}
