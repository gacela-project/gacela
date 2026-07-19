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

    public function test_empty_graph(): void
    {
        self::assertSame("\n", (new TextGraphFormatter())->format([]));
        self::assertSame("graph TD\n", (new MermaidGraphFormatter())->format([]));
        self::assertSame("digraph modules {\n}\n", (new GraphvizGraphFormatter())->format([]));
        self::assertSame("[]\n", (new JsonGraphFormatter())->format([]));
    }
}
