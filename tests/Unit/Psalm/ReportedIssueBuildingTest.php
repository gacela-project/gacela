<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Psalm\Issue\GacelaSuffixExtends;
use Gacela\Psalm\ReportedIssues;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PhpParser\Node\Stmt\ClassLike;
use PHPUnit\Framework\TestCase;
use Psalm\Config;
use Psalm\StatementsSource;
use ReflectionClass;
use ReflectionProperty;

/**
 * Which issue class, which message and which line a finding turns into.
 *
 * Handing the result to `IssueBuffer` needs a live `ProjectAnalyzer`, so that
 * last step is only reachable through the real-Psalm tests -- but everything
 * decided before it is reachable here.
 */
final class ReportedIssueBuildingTest extends TestCase
{
    private ?Config $previousConfig = null;

    protected function setUp(): void
    {
        $instance = new ReflectionProperty(Config::class, 'instance');
        $this->previousConfig = $instance->getValue();
        $instance->setValue(null, (new ReflectionClass(Config::class))->newInstanceWithoutConstructor());
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Config::class, 'instance'))->setValue(null, $this->previousConfig);
    }

    public function test_an_identifier_becomes_its_own_issue_class(): void
    {
        $issue = $this->toIssue(new Violation('Something is wrong', 'gacela.suffixExtends'));

        self::assertInstanceOf(GacelaSuffixExtends::class, $issue);
    }

    /**
     * A rule with no issue class of its own would have no suppression key, so
     * nothing is reported under some catch-all name.
     */
    public function test_an_unknown_identifier_becomes_nothing(): void
    {
        self::assertNull($this->toIssue(new Violation('Something is wrong', 'gacela.notARule')));
    }

    public function test_a_message_without_a_tip_is_passed_through(): void
    {
        $issue = $this->toIssue(new Violation('Something is wrong', 'gacela.suffixExtends'));

        self::assertSame('Something is wrong', $issue?->message);
    }

    /**
     * Psalm has nowhere else to put a tip, so it rides along in the message
     * rather than being dropped.
     */
    public function test_a_tip_rides_along_in_the_message(): void
    {
        $issue = $this->toIssue(new Violation('Something is wrong', 'gacela.suffixExtends', 'Do this instead.'));

        self::assertSame('Something is wrong Do this instead.', $issue?->message);
    }

    public function test_a_finding_without_a_node_is_located_at_the_analysed_one(): void
    {
        $issue = $this->toIssue(new Violation('Something is wrong', 'gacela.suffixExtends'));

        self::assertSame(2, $issue?->code_location->getLineNumber());
    }

    /**
     * A finding that names its own node is located there instead -- which is
     * what puts a facade method's drift on the method, not on the class.
     */
    public function test_a_finding_with_a_node_is_located_at_that_node(): void
    {
        $method = $this->classNode()->getMethods()[0];

        $issue = $this->toIssue(
            new Violation('Something is wrong', 'gacela.suffixExtends', null, $method),
        );

        self::assertSame(4, $issue?->code_location->getLineNumber());
    }

    private function toIssue(Violation $violation): ?object
    {
        $node = $this->classNode();

        $source = $this->createStub(StatementsSource::class);
        $source->method('getFilePath')->willReturn('/tmp/Whatever.php');
        $source->method('getFileName')->willReturn('Whatever.php');
        $source->method('getAliasedClassesFlipped')->willReturn([]);

        return ReportedIssues::toIssue($violation, $node, $source);
    }

    private function classNode(): ClassLike
    {
        $source = <<<'PHP'
            <?php
            final class CheckoutFacade
            {
                public function doThing()
                {
                    return 1;
                }
            }
            PHP;

        return ParseSource::classIn($source);
    }
}
