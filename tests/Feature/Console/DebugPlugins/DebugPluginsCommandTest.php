<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugPlugins;

use Gacela\Console\Infrastructure\Command\DebugPluginsCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Aliased;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Bundle;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Central;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Coupon;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Discount;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Loyalty;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function array_column;
use function dirname;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * A member joining by attribute is named nowhere in `gacela.php`; this is
 * where it is seen, in the order the stack is read.
 */
final class DebugPluginsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(dirname(__DIR__, 2) . '/Framework/PluginAttribute', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->setProjectNamespaces(['GacelaTest\Feature\Framework\PluginAttribute\Checkout']);
            $config->addPluginStack(Discount::class, [Central::class]);
            $config->tag([Central::class], 'discounts');
        });
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_the_stack_lists_declared_members_then_attribute_ones(): void
    {
        $report = $this->json();

        self::assertSame(
            [Central::class, Loyalty::class, Aliased::class, Bundle::class, Coupon::class],
            array_column($report['stacks'], 'member'),
        );
        self::assertSame('gacela.php', $report['stacks'][0]['source']);
        self::assertStringStartsWith('#[Plugin] priority', $report['stacks'][1]['source']);
        self::assertSame([['tag' => 'discounts', 'id' => Central::class, 'source' => 'gacela.php']], $report['tags']);
    }

    public function test_the_text_report_names_each_section(): void
    {
        $tester = new CommandTester(new DebugPluginsCommand());
        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Plugin stacks', $display);
        self::assertStringContainsString('Tags', $display);
        self::assertStringContainsString('#[Plugin] priority', $display);
        self::assertStringNotContainsString('Listeners by #[AsListener]', $display);
    }

    /**
     * @return array{stacks: list<array{contract: string, member: string, source: string}>, tags: list<array{tag: string, id: string, source: string}>}
     */
    private function json(): array
    {
        $tester = new CommandTester(new DebugPluginsCommand());
        $tester->execute(['--json' => true]);

        /** @var array{stacks: list<array{contract: string, member: string, source: string}>, tags: list<array{tag: string, id: string, source: string}>} */
        return json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
    }
}
