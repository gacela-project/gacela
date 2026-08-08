<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\Rules\FacadeInterfaceInSyncAnalyser;
use Gacela\StaticAnalysis\Rules\FacadeOnlyDelegatesAnalyser;
use Gacela\StaticAnalysis\Rules\FactoryDoesNotCallFacadeAnalyser;
use Gacela\StaticAnalysis\Rules\SuffixExtendsAnalyser;
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
    public static function afterStatementAnalysis(AfterClassLikeAnalysisEvent $event): ?bool
    {
        $node = $event->getStmt();
        $source = $event->getStatementsSource();
        $class = new StorageAnalysedClass($event->getClasslikeStorage(), $event->getCodebase());

        foreach (self::classAnalysers() as $analyser) {
            ReportedIssues::report($analyser->analyse($node, $class), $node, $source);
        }

        $facadeMethods = new FacadeOnlyDelegatesAnalyser();
        foreach ($node->getMethods() as $method) {
            ReportedIssues::report($facadeMethods->analyse($method, $class), $method, $source);
        }

        return null;
    }

    /**
     * @return list<ClassAnalyserInterface>
     */
    private static function classAnalysers(): array
    {
        return [
            new SuffixExtendsAnalyser('Facade', AbstractFacade::class),
            new SuffixExtendsAnalyser('Factory', AbstractFactory::class),
            new SuffixExtendsAnalyser('Provider', AbstractProvider::class),
            new SuffixExtendsAnalyser('Config', AbstractConfig::class),
            new FactoryDoesNotCallFacadeAnalyser(),
            new FacadeInterfaceInSyncAnalyser(),
        ];
    }
}
