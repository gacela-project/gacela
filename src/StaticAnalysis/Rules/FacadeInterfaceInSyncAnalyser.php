<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\Framework\AbstractFacade;
use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Stmt\ClassLike;

use function sprintf;
use function str_starts_with;

/**
 * Reports public Facade methods missing from the Facade's own `*FacadeInterface`.
 *
 * Only this direction can drift. PHP already rejects a class that fails to
 * implement an interface method, so the interface can never gain a method the
 * facade lacks -- but the facade grows public methods the interface never hears
 * about, and consumers type-hinting the interface silently cannot reach them.
 * That drift is invisible until someone reads both files side by side, and the
 * correction is breaking by then.
 *
 * The rule only applies when a facade explicitly implements the interface named
 * after it (`FooFacade` implements `FooFacadeInterface`), which is the author
 * opting in. A facade implementing unrelated interfaces is not drifting.
 */
final class FacadeInterfaceInSyncAnalyser implements ClassAnalyserInterface
{
    /**
     * @return list<Violation>
     */
    public function analyse(ClassLike $node, AnalysedClassInterface $class): array
    {
        if (!$class->extendsClass(AbstractFacade::class)) {
            return [];
        }

        $facadeInterface = $this->matchingInterface($class);
        if ($facadeInterface === null) {
            return [];
        }

        $violations = [];

        foreach ($node->getMethods() as $method) {
            $methodName = $method->name->toString();
            if (!$method->isPublic()) {
                continue;
            }

            // `__construct` and friends are not part of the facade's surface.
            if (str_starts_with($methodName, '__')) {
                continue;
            }

            if ($class->interfaceHasMethod($facadeInterface, $methodName)) {
                continue;
            }

            $violations[] = new Violation(
                sprintf(
                    'Facade method %s::%s() is missing from %s. Consumers type-hinting the interface cannot reach it: declare it in the interface, or make the method non-public.',
                    $class->name(),
                    $methodName,
                    $facadeInterface,
                ),
                'gacela.facadeInterfaceDrift',
                $method->getStartLine(),
            );
        }

        return $violations;
    }

    private function matchingInterface(AnalysedClassInterface $class): ?string
    {
        $expected = ShortName::of($class->name()) . 'Interface';

        foreach ($class->interfaceNames() as $interface) {
            if (ShortName::of($interface) === $expected) {
                return $interface;
            }
        }

        return null;
    }
}
