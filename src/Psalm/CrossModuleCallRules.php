<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\StaticAnalysis\Rules\CrossModuleMethodCallAnalyser;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use Psalm\NodeTypeProvider;
use Psalm\Plugin\EventHandler\AfterExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Union;

/**
 * The half of the cross-module check that resolves a method call's receiver by
 * type, for the boundary a call site never names.
 *
 * An expression handler rather than a class one: only the type known at the call
 * site says what the receiver is.
 *
 * @see CrossModuleRules for the half that matches written names
 */
final class CrossModuleCallRules implements AfterExpressionAnalysisInterface
{
    private static ?CrossModuleMethodCallAnalyser $analyser = null;

    /**
     * Null turns the rule back off, so invoking the plugin twice leaves the
     * state its latest config asked for rather than whatever a previous one set.
     */
    public static function configure(?CrossModuleSettings $settings): void
    {
        self::$analyser = $settings instanceof CrossModuleSettings
            ? new CrossModuleMethodCallAnalyser(
                $settings->rootNamespace,
                $settings->modulePathSegments,
                $settings->sharedNamespaces,
                $settings->ignoreReceivers,
            )
            : null;
    }

    public static function isConfigured(): bool
    {
        return self::$analyser instanceof CrossModuleMethodCallAnalyser;
    }

    public static function afterExpressionAnalysis(AfterExpressionAnalysisEvent $event): ?bool
    {
        $analyser = self::$analyser;
        if (!$analyser instanceof CrossModuleMethodCallAnalyser) {
            return null;
        }

        $expr = $event->getExpr();
        if (!$expr instanceof MethodCall && !$expr instanceof NullsafeMethodCall) {
            return null;
        }

        $source = $event->getStatementsSource();
        $callingClass = $source->getFQCLN();
        if ($callingClass === null) {
            return null;
        }

        ReportedIssues::report(
            self::violationsFor($expr, $callingClass, $source->getNodeTypeProvider()),
            $expr,
            $source,
        );

        return null;
    }

    /**
     * What the rule finds, apart from the reporting -- see
     * {@see ClassRules::violationsIn()} for why the two are split.
     *
     * @return list<Violation>
     */
    public static function violationsFor(
        MethodCall|NullsafeMethodCall $expr,
        string $callingClass,
        NodeTypeProvider $types,
    ): array {
        return self::$analyser instanceof \Gacela\StaticAnalysis\Rules\CrossModuleMethodCallAnalyser
            ? self::$analyser->analyse($callingClass, self::receiverClasses($expr, $types))
            : [];
    }

    /**
     * Empty when Psalm could not tell, which the analyser reads as "no evidence"
     * rather than "no violation to find".
     *
     * @return list<string>
     */
    private static function receiverClasses(MethodCall|NullsafeMethodCall $expr, NodeTypeProvider $types): array
    {
        $type = $types->getType($expr->var);
        if (!$type instanceof Union) {
            return [];
        }

        $classes = [];

        foreach ($type->getAtomicTypes() as $atomic) {
            if ($atomic instanceof TNamedObject) {
                $classes[] = $atomic->value;
            }
        }

        return $classes;
    }
}
