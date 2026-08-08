<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\StaticAnalysis\Rules\CrossModuleMethodCallAnalyser;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
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
            $analyser->analyse($callingClass, self::receiverClasses($event)),
            $expr,
            $source,
        );

        return null;
    }

    /**
     * Empty when Psalm could not tell, which the analyser reads as "no evidence"
     * rather than "no violation to find".
     *
     * @return list<string>
     */
    private static function receiverClasses(AfterExpressionAnalysisEvent $event): array
    {
        /** @var MethodCall|NullsafeMethodCall $expr */
        $expr = $event->getExpr();

        $type = $event->getStatementsSource()->getNodeTypeProvider()->getType($expr->var);
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
