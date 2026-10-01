<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ResolvedName;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;

use function array_filter;
use function array_pop;
use function array_values;
use function count;
use function explode;
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

        if (!$this->couldBeResolvedAsThePillar($className)) {
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

        if ($this->isConfigExtender($classNode)) {
            return [];
        }

        // Extending the base is the fix only for the module's real pillar: on
        // any other class it makes a second candidate that resolution picks up
        // by name and fails on with nothing pointing at the cause.
        return [
            new Violation(
                sprintf('Class %s should extend %s', $className, $this->expectedParent),
                'gacela.suffixExtends',
                sprintf(
                    "Rename it so it does not end in %s. Extend %s only if it is its module's %s.",
                    $this->suffix,
                    $this->expectedParent,
                    $this->suffix,
                ),
            ),
        ];
    }

    /**
     * A Factory, Config or Provider is found by name, beside the module's
     * Facade: `{Module}{Suffix}` or the bare suffix, `{Module}` being the last
     * segment of the namespace. Any other class with the suffix is never picked
     * up, so telling it to extend the pillar base is wrong advice. A Facade is
     * whatever class the caller instantiates, so every `*Facade` is a candidate.
     */
    private function couldBeResolvedAsThePillar(string $className): bool
    {
        if ($this->suffix === ResolvableTypes::FACADE) {
            return true;
        }

        $parts = explode('\\', $className);
        $shortName = array_pop($parts);
        $module = $parts === [] ? '' : $parts[count($parts) - 1];

        return $shortName === $this->suffix || $shortName === $module . $this->suffix;
    }

    /**
     * The invokable `extendGacelaConfig()` takes: no parent, no interface, one
     * `__invoke(GacelaConfig)`, and `*Config` is its natural name. The container
     * builds it, so a constructor may sit beside the `__invoke`.
     */
    private function isConfigExtender(Class_ $classNode): bool
    {
        $methods = array_values(array_filter(
            $classNode->getMethods(),
            static fn (ClassMethod $method): bool => $method->name->toLowerString() !== '__construct',
        ));

        if (count($methods) !== 1 || $methods[0]->name->toLowerString() !== '__invoke') {
            return false;
        }

        $params = $methods[0]->params;

        return count($params) === 1
            && $params[0]->type instanceof Name
            && ResolvedName::of($params[0]->type) === GacelaConfig::class;
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
