<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\MethodAnalyserInterface;
use Gacela\StaticAnalysis\Rules\CacheableKeyIgnoresArgumentsAnalyser;
use Gacela\StaticAnalysis\Rules\CacheableWithoutCachedCallAnalyser;
use Gacela\StaticAnalysis\Rules\FacadeInterfaceInSyncAnalyser;
use Gacela\StaticAnalysis\Rules\FacadeOnlyDelegatesAnalyser;
use Gacela\StaticAnalysis\Rules\FactoryDoesNotCallFacadeAnalyser;
use Gacela\StaticAnalysis\Rules\SuffixExtendsAnalyser;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Stmt\ClassLike;
use Psalm\Plugin\EventHandler\AfterClassLikeAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeAnalysisEvent;

/**
 * Runs the Gacela architecture rules under Psalm.
 *
 * The rules themselves live in `Gacela\StaticAnalysis` and are the same objects
 * `phpstan-gacela.neon` registers; this is the adapting, and nothing else. Two
 * implementations of "what counts as a delegation" would drift, which is the
 * failure `FacadeInterfaceInSyncAnalyser` exists to catch.
 *
 * The facade-method rule runs from here rather than from an
 * `AfterFunctionLikeAnalysis` handler of its own: Psalm hands a function-like
 * over without the class storage the rule needs, and the only routes back to it
 * are `@internal` to Psalm. Walking the methods of a class already in hand costs
 * nothing and keeps the plugin off Psalm's internals entirely.
 *
 * `CrossModuleViaFacadeAnalyser` is absent on purpose: it needs the consumer's
 * root namespace, so it is registered from plugin config rather than by default.
 */
final class ClassRules implements AfterClassLikeAnalysisInterface
{
    /** @var list<ClassAnalyserInterface>|null */
    private static ?array $classAnalysers = null;

    /** @var list<MethodAnalyserInterface>|null */
    private static ?array $methodAnalysers = null;

    public static function afterStatementAnalysis(AfterClassLikeAnalysisEvent $event): ?bool
    {
        $node = $event->getStmt();

        ReportedIssues::report(
            self::violationsIn($node, new StorageAnalysedClass($event->getClasslikeStorage(), $event->getCodebase())),
            $node,
            $event->getStatementsSource(),
        );

        return null;
    }

    /**
     * Everything the class-level rules find, each pinned to the node it belongs
     * to -- a facade method's finding belongs on the method, not on the class.
     *
     * Kept apart from the reporting so it can be driven directly: reporting goes
     * through Psalm's IssueBuffer, which needs a live ProjectAnalyzer that a unit
     * test has no way to supply. Without the split, the only proof these rules
     * are wired up at all was a subprocess that coverage cannot see.
     *
     * @return list<Violation>
     */
    public static function violationsIn(ClassLike $node, AnalysedClassInterface $class): array
    {
        $violations = [];

        foreach (self::classAnalysers() as $analyser) {
            foreach ($analyser->analyse($node, $class) as $violation) {
                $violations[] = $violation;
            }
        }

        foreach ($node->getMethods() as $method) {
            foreach (self::methodAnalysers() as $analyser) {
                foreach ($analyser->analyse($method, $class) as $violation) {
                    $violations[] = $violation->at($method);
                }
            }
        }

        return $violations;
    }

    /**
     * The method-level rules, listed the way the class-level ones are. Naming
     * them one at a time meant a property, a `??=` and a loop per rule.
     *
     * @return list<MethodAnalyserInterface>
     */
    private static function methodAnalysers(): array
    {
        return self::$methodAnalysers ??= [
            new FacadeOnlyDelegatesAnalyser(),
            new CacheableKeyIgnoresArgumentsAnalyser(),
        ];
    }

    /**
     * Built once. The rules hold only their configuration, and this runs for
     * every class-like in a project.
     *
     * @return list<ClassAnalyserInterface>
     */
    private static function classAnalysers(): array
    {
        return self::$classAnalysers ??= [
            new CacheableWithoutCachedCallAnalyser(),
            new SuffixExtendsAnalyser('Facade', AbstractFacade::class),
            new SuffixExtendsAnalyser('Factory', AbstractFactory::class),
            new SuffixExtendsAnalyser('Provider', AbstractProvider::class),
            new SuffixExtendsAnalyser('Config', AbstractConfig::class),
            new FactoryDoesNotCallFacadeAnalyser(),
            new FacadeInterfaceInSyncAnalyser(),
        ];
    }
}
