<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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
        $classNode = $this->pillarCandidate($node);

        if (!$classNode instanceof Class_) {
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

        // A class that already has a parent cannot take this advice either:
        // PHP has single inheritance, so the only way out would be a rename or
        // a baseline entry -- the same reason interfaces, traits and enums go
        // unreported. Its whole ancestry was checked for the pillar just above,
        // so reaching here means the parent belongs to another hierarchy: a
        // `GoogleAuthProvider extends AbstractOAuthProvider` has nothing to do
        // with Gacela, and this rule runs inside every consumer's build.
        if ($classNode->extends instanceof Name) {
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
    private function pillarCandidate(ClassLike $node): ?Class_
    {
        if (!$node instanceof Class_) {
            return null;
        }

        return $node->name instanceof Identifier ? $node : null;
    }
}
