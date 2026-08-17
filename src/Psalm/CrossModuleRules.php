<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\Rules\CrossModuleViaFacadeAnalyser;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Stmt\ClassLike;
use Psalm\Plugin\EventHandler\AfterClassLikeAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeAnalysisEvent;

/**
 * The half of the cross-module check that matches names written in the source.
 *
 * Registered only when the consumer supplies a root namespace, so the analyser
 * lives in a static: Psalm registers handlers by class-string and calls them
 * statically, leaving nowhere else to put configuration.
 *
 * @see CrossModuleCallRules for the half that resolves receivers by type
 */
final class CrossModuleRules implements AfterClassLikeAnalysisInterface
{
    private static ?CrossModuleViaFacadeAnalyser $analyser = null;

    /**
     * Null turns the rule back off, so invoking the plugin twice leaves the
     * state its latest config asked for rather than whatever a previous one set.
     */
    public static function configure(?CrossModuleSettings $settings): void
    {
        self::$analyser = $settings instanceof CrossModuleSettings
            ? new CrossModuleViaFacadeAnalyser(
                $settings->rootNamespace,
                $settings->modulePathSegments,
                $settings->sharedNamespaces,
                $settings->publicApiSegments,
            )
            : null;
    }

    public static function isConfigured(): bool
    {
        return self::$analyser instanceof CrossModuleViaFacadeAnalyser;
    }

    public static function afterStatementAnalysis(AfterClassLikeAnalysisEvent $event): ?bool
    {
        $analyser = self::$analyser;
        if (!$analyser instanceof CrossModuleViaFacadeAnalyser) {
            return null;
        }

        $node = $event->getStmt();

        ReportedIssues::report(
            self::violationsIn($node, new StorageAnalysedClass($event->getClasslikeStorage(), $event->getCodebase())),
            $node,
            $event->getStatementsSource(),
        );

        return null;
    }

    /**
     * What the rule finds, apart from the reporting -- see
     * {@see ClassRules::violationsIn()} for why the two are split.
     *
     * @return list<Violation>
     */
    public static function violationsIn(ClassLike $node, AnalysedClassInterface $class): array
    {
        return self::$analyser instanceof \Gacela\StaticAnalysis\Rules\CrossModuleViaFacadeAnalyser ? self::$analyser->analyse($node, $class) : [];
    }
}
