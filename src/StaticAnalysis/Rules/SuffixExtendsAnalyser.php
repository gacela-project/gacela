<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;

use function sprintf;
use function str_ends_with;

/**
 * A class named after a pillar has to be one: `*Facade` extends
 * `AbstractFacade`, `*Factory` extends `AbstractFactory`, and so on.
 *
 * One instance checks one pillar, so the four registrations differ only by their
 * arguments.
 */
final class SuffixExtendsAnalyser implements ClassAnalyserInterface
{
    public function __construct(
        private readonly string $suffix,
        private readonly string $expectedParent,
    ) {
    }

    /**
     * @return list<Violation>
     */
    public function analyse(ClassLike $node, AnalysedClassInterface $class): array
    {
        // An anonymous class has no name to carry a suffix, and nothing a
        // consumer could rename if it were reported.
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $className = $class->name();

        if (!str_ends_with(ShortName::of($className), $this->suffix)) {
            return [];
        }

        // The base class is itself named after the pillar it defines.
        if ($className === $this->expectedParent) {
            return [];
        }

        if ($class->extendsClass($this->expectedParent)) {
            return [];
        }

        return [
            new Violation(
                sprintf('Class %s should extend %s', $className, $this->expectedParent),
                'gacela.suffixExtends',
            ),
        ];
    }
}
