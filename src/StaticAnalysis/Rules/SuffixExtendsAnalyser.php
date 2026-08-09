<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
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
        if (!$this->couldExtendAPillar($node)) {
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
                sprintf(
                    'Extend %s, or rename it so it does not end in %s.',
                    $this->expectedParent,
                    $this->suffix,
                ),
            ),
        ];
    }

    /**
     * Interfaces, traits and enums cannot extend a class at all, so telling one
     * of them to is advice it is impossible to take -- the only way out would be
     * a baseline entry. An anonymous class has no name to carry a suffix, and
     * nothing a consumer could rename if it were reported.
     */
    private function couldExtendAPillar(ClassLike $node): bool
    {
        return $node instanceof Class_ && $node->name instanceof Identifier;
    }
}
