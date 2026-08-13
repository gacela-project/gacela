<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\Rules\ServiceMapMissingAnalyser;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Stmt\ClassLike;
use Psalm\Plugin\EventHandler\AfterClassLikeAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeAnalysisEvent;

/**
 * Pillar accessors still resolved from a `@method` docblock, checked per class.
 *
 * Opt-in, and for a reason the other rules do not share: what it reports is not
 * wrong on 2.x. It is a deprecation, and a project mid-migration would otherwise
 * have its build fail over code that works -- so turning it on is the decision
 * to start the 3.0 migration, taken by the project rather than by an upgrade.
 *
 * Registered only when the consumer asks for it, so the analyser lives in a
 * static: Psalm registers handlers by class-string and calls them statically,
 * leaving nowhere else to put the on/off state.
 *
 * @see ServiceMapMissingAnalyser for what is checked and why
 */
final class ServiceMapMissingRules implements AfterClassLikeAnalysisInterface
{
    private static ?ServiceMapMissingAnalyser $analyser = null;

    /**
     * False turns the rule back off, so invoking the plugin twice leaves the
     * state its latest config asked for rather than whatever a previous one set.
     */
    public static function configure(bool $enabled): void
    {
        self::$analyser = $enabled ? new ServiceMapMissingAnalyser() : null;
    }

    public static function isConfigured(): bool
    {
        return self::$analyser instanceof ServiceMapMissingAnalyser;
    }

    public static function afterStatementAnalysis(AfterClassLikeAnalysisEvent $event): ?bool
    {
        if (!self::$analyser instanceof ServiceMapMissingAnalyser) {
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
        return self::$analyser instanceof ServiceMapMissingAnalyser
            ? self::$analyser->analyse($node, $class)
            : [];
    }
}
