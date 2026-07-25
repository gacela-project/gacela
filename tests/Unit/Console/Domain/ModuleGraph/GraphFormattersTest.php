<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph;

use Gacela\Console\Domain\ModuleGraph\GraphvizGraphFormatter;
use Gacela\Console\Domain\ModuleGraph\JsonGraphFormatter;
use Gacela\Console\Domain\ModuleGraph\MermaidGraphFormatter;
use Gacela\Console\Domain\ModuleGraph\TextGraphFormatter;
use PHPUnit\Framework\TestCase;

final class GraphFormattersTest extends TestCase
{
    private const GRAPH = [
        'App\Checkout' => ['App\Payment', 'App\Stock'],
        'App\Payment' => [],
    ];

    public function test_text_format(): void
    {
        $expected = <<<'TXT'
App\Checkout (2)
  -> App\Payment
  -> App\Stock
App\Payment (0)

TXT;
        self::assertSame($expected, (new TextGraphFormatter())->format(self::GRAPH));
    }

    public function test_mermaid_format(): void
    {
        $expected = <<<'TXT'
graph TD
    App.Checkout --> App.Payment
    App.Checkout --> App.Stock
    App.Payment

TXT;
        self::assertSame($expected, (new MermaidGraphFormatter())->format(self::GRAPH));
    }

    public function test_graphviz_format(): void
    {
        $expected = <<<'TXT'
digraph modules {
    "App\Checkout" -> "App\Payment";
    "App\Checkout" -> "App\Stock";
    "App\Payment";
}

TXT;
        self::assertSame($expected, (new GraphvizGraphFormatter())->format(self::GRAPH));
    }

    public function test_json_format(): void
    {
        $decoded = json_decode((new JsonGraphFormatter())->format(self::GRAPH), true);

        self::assertSame(self::GRAPH, $decoded);
    }

    /**
     * The flags are the format: without them the output is one dense line with
     * escaped separators, which is unreadable in a diff and in a PR artifact.
     */
    public function test_json_format_is_pretty_printed_with_unescaped_slashes(): void
    {
        $json = (new JsonGraphFormatter())->format(['App\Checkout' => ['App/Sub']]);

        self::assertStringContainsString("{\n    \"App", $json);
        self::assertStringContainsString('App/Sub', $json);
        self::assertStringNotContainsString('App\/Sub', $json);
    }

    /**
     * A module with no dependencies must not end the loop: real graphs are
     * mostly leaf modules, and one early leaf would hide everything after it.
     */
    public function test_a_dependency_free_module_does_not_truncate_the_output(): void
    {
        $graph = [
            'App\Leaf' => [],
            'App\Checkout' => ['App\Payment'],
        ];

        self::assertSame(
            "graph TD\n    App.Leaf\n    App.Checkout --> App.Payment\n",
            (new MermaidGraphFormatter())->format($graph),
        );
        self::assertSame(
            "digraph modules {\n    \"App\Leaf\";\n    \"App\Checkout\" -> \"App\Payment\";\n}\n",
            (new GraphvizGraphFormatter())->format($graph),
        );
        self::assertStringContainsString('App\Checkout (1)', (new TextGraphFormatter())->format($graph));
    }

    public function test_empty_graph(): void
    {
        self::assertSame("\n", (new TextGraphFormatter())->format([]));
        self::assertSame("graph TD\n", (new MermaidGraphFormatter())->format([]));
        self::assertSame("digraph modules {\n}\n", (new GraphvizGraphFormatter())->format([]));
        self::assertSame("[]\n", (new JsonGraphFormatter())->format([]));
    }
}
