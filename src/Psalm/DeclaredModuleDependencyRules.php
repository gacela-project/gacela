<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;
use Gacela\StaticAnalysis\Rules\DeclaredModuleDependencyAnalyser;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Stmt\ClassLike;
use Psalm\Plugin\EventHandler\AfterClassLikeAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeAnalysisEvent;

/**
 * The dependencies a project declared it would not have, checked per class.
 *
 * Registered only when the consumer supplies a rules file, so the analyser lives
 * in a static: Psalm registers handlers by class-string and calls them
 * statically, leaving nowhere else to put configuration.
 *
 * @see DeclaredModuleDependencyAnalyser for what is checked and why
 */
final class DeclaredModuleDependencyRules implements AfterClassLikeAnalysisInterface
{
    private static ?DeclaredModuleDependencyAnalyser $analyser = null;

    /**
     * Null turns the rule back off, so invoking the plugin twice leaves the
     * state its latest config asked for rather than whatever a previous one set.
     */
    public static function configure(?ModuleRulesSettings $settings): void
    {
        self::$analyser = $settings instanceof ModuleRulesSettings
            ? new DeclaredModuleDependencyAnalyser(
                $settings->rootNamespace,
                ModuleRuleSet::fromFile($settings->file),
                $settings->modulePathSegments,
                $settings->sharedNamespaces,
            )
            : null;
    }

    public static function isConfigured(): bool
    {
        return self::$analyser instanceof DeclaredModuleDependencyAnalyser;
    }

    public static function afterStatementAnalysis(AfterClassLikeAnalysisEvent $event): ?bool
    {
        if (!self::$analyser instanceof DeclaredModuleDependencyAnalyser) {
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
        return self::$analyser instanceof DeclaredModuleDependencyAnalyser
            ? self::$analyser->analyse($node, $class)
            : [];
    }
}
