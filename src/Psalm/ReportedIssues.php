<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\Psalm\Issue\GacelaCrossModuleAccess;
use Gacela\Psalm\Issue\GacelaCrossModuleMethodCall;
use Gacela\Psalm\Issue\GacelaFacadeInstantiation;
use Gacela\Psalm\Issue\GacelaFacadeInterfaceDrift;
use Gacela\Psalm\Issue\GacelaFacadeOnlyDelegates;
use Gacela\Psalm\Issue\GacelaFactoryFacadeAccess;
use Gacela\Psalm\Issue\GacelaSuffixExtends;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node;
use Psalm\CodeLocation;
use Psalm\Issue\PluginIssue;
use Psalm\IssueBuffer;
use Psalm\StatementsSource;

use function array_keys;

/**
 * Turns the host-agnostic findings into Psalm's own issue objects.
 *
 * PHPStan suppresses on an error identifier; Psalm suppresses on an issue class
 * name. This is where one becomes the other, and the only place that mapping
 * exists.
 *
 * The map has to cover every identifier a rule can produce, or that rule ships
 * with no way to turn it off. `ReportedIssuesTest` is what holds it complete;
 * at analysis time an unknown identifier is skipped rather than crashing a
 * consumer's run over a mistake of ours.
 *
 * @internal
 */
final class ReportedIssues
{
    /** @var array<string, class-string<PluginIssue>> */
    private const BY_IDENTIFIER = [
        'gacela.suffixExtends' => GacelaSuffixExtends::class,
        'gacela.facadeOnlyDelegates' => GacelaFacadeOnlyDelegates::class,
        'gacela.factoryInstantiatesFacade' => GacelaFacadeInstantiation::class,
        'gacela.factoryCallsGetFacade' => GacelaFactoryFacadeAccess::class,
        'gacela.facadeInterfaceDrift' => GacelaFacadeInterfaceDrift::class,
        'gacela.crossModuleWithoutFacade' => GacelaCrossModuleAccess::class,
        'gacela.crossModuleMethodCall' => GacelaCrossModuleMethodCall::class,
    ];

    /**
     * @param list<Violation> $violations
     * @param Node            $analysedNode the node to locate a finding at when
     *                                      it carries none of its own
     */
    public static function report(array $violations, Node $analysedNode, StatementsSource $source): void
    {
        foreach ($violations as $violation) {
            $issue = self::toIssue($violation, $analysedNode, $source);

            if ($issue instanceof PluginIssue) {
                IssueBuffer::maybeAdd($issue, $source->getSuppressedIssues());
            }
        }
    }

    /**
     * One finding as the issue Psalm will report, or null when no rule owns the
     * identifier.
     *
     * Kept apart from {@see report()} so it can be driven directly: handing an
     * issue to `IssueBuffer` needs a live `ProjectAnalyzer`, which a unit test
     * has no way to supply -- but which class, which message and which line are
     * decided here, and those are worth pinning.
     *
     * @param Node $analysedNode the node to locate a finding at when it carries
     *                           none of its own
     */
    public static function toIssue(Violation $violation, Node $analysedNode, StatementsSource $source): ?PluginIssue
    {
        $issue = self::issueFor($violation->identifier);
        if ($issue === null) {
            return null;
        }

        return new $issue(
            // Psalm has no separate channel for a tip, so the correction rides
            // along in the message rather than being dropped -- the two hosts
            // should tell you the same thing.
            $violation->messageWithTip(),
            new CodeLocation($source, $violation->node ?? $analysedNode),
        );
    }

    /**
     * The identifiers this can report, for the test that holds the map complete
     * against the rules.
     *
     * @return list<string>
     */
    public static function mappedIdentifiers(): array
    {
        return array_keys(self::BY_IDENTIFIER);
    }

    /**
     * @return class-string<PluginIssue>|null
     */
    public static function issueFor(string $identifier): ?string
    {
        return self::BY_IDENTIFIER[$identifier] ?? null;
    }
}
