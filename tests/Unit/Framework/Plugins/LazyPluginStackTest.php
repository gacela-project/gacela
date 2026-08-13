<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins;

use Gacela\Container\Container;
use Gacela\Framework\Exception\PluginStackException;
use Gacela\Framework\Plugins\LazyPluginStack;
use GacelaTest\Unit\Framework\Plugins\Fixtures\CountingDecorator;
use GacelaTest\Unit\Framework\Plugins\Fixtures\Decorator;
use GacelaTest\Unit\Framework\Plugins\Fixtures\NotADecorator;
use GacelaTest\Unit\Framework\Plugins\Fixtures\UppercaseDecorator;
use PHPUnit\Framework\TestCase;

final class LazyPluginStackTest extends TestCase
{
    protected function tearDown(): void
    {
        CountingDecorator::$built = 0;
    }

    public function test_an_empty_stack_says_so(): void
    {
        $stack = $this->stack([]);

        self::assertTrue($stack->isEmpty());
        self::assertCount(0, $stack);
        self::assertSame([], $stack->all());
    }

    public function test_it_yields_every_plugin_in_declaration_order(): void
    {
        $stack = $this->stack([UppercaseDecorator::class, CountingDecorator::class]);

        $decorated = 'x';
        foreach ($stack as $decorator) {
            $decorated = $decorator->decorate($decorated);
        }

        self::assertSame('X!', $decorated);
        self::assertCount(2, $stack);
        self::assertFalse($stack->isEmpty());
    }

    /**
     * Declaring is not building: a stack nobody iterates costs nothing, which
     * is what lets a module declare an extension point it rarely uses.
     */
    public function test_declaring_a_plugin_does_not_build_it(): void
    {
        $this->stack([CountingDecorator::class]);

        self::assertSame(0, CountingDecorator::$built);
    }

    /**
     * Iterating twice yields the same plugins, however they are bound -- the
     * reason the stack keeps its own instances rather than trusting the
     * container's binding style.
     */
    public function test_a_plugin_is_built_once_however_often_it_is_read(): void
    {
        $container = new Container();
        $container->bind(CountingDecorator::class, static fn (): CountingDecorator => new CountingDecorator());

        $stack = new LazyPluginStack(Decorator::class, [CountingDecorator::class], $container);

        $first = $stack->all()[0];
        $second = $stack->all()[0];
        iterator_to_array($stack);

        self::assertSame($first, $second);
        self::assertSame(1, CountingDecorator::$built);
    }

    /**
     * A class name in gacela.php is a string until something loads it, so the
     * contract is checked on first resolve -- and the failure names the class
     * and the stack, instead of a TypeError wherever the consumer used it.
     */
    /**
     * A class name in `gacela.php` is a string until something loads it, and
     * the container answers `null` for one that resolves to nothing -- so the
     * contract check reported a missing class as one that "does not implement"
     * the contract, sending the reader to inspect an `implements` clause on a
     * file that is not there. A typo in a plugin's class name is the ordinary
     * way to arrive here.
     */
    public function test_a_plugin_class_that_does_not_exist_says_so(): void
    {
        /** @var class-string $missing */
        $missing = 'GacelaTest\\Unit\\Framework\\Plugins\\Fixtures\\NoSuchDecorator';
        $stack = $this->stack([$missing]);

        try {
            $stack->all();
            self::fail('Expected a PluginStackException');
        } catch (PluginStackException $pluginStackException) {
            $message = $pluginStackException->getMessage();

            self::assertStringContainsString('no such class exists', $message);
            self::assertStringContainsString('NoSuchDecorator', $message);
            self::assertStringNotContainsString('does not implement', $message);
            // What went wrong first, what to try about it after.
            self::assertStringStartsWith('"GacelaTest', $message);
        }
    }

    /**
     * The tips for a class that does not exist, which is what this is: a
     * namespace typo or an autoloader that has not seen the file.
     */
    public function test_a_missing_plugin_class_carries_the_tips_for_one(): void
    {
        /** @var class-string $missing */
        $missing = 'GacelaTest\\Unit\\Framework\\Plugins\\Fixtures\\NoSuchDecorator';

        $this->expectExceptionMessage("Run 'composer dump-autoload' to refresh autoloader");

        $this->stack([$missing])->all();
    }

    public function test_a_plugin_that_does_not_implement_the_contract_is_refused_by_name(): void
    {
        $stack = $this->stack([NotADecorator::class]);

        $this->expectException(PluginStackException::class);
        $this->expectExceptionMessage('NotADecorator');
        $this->expectExceptionMessage(Decorator::class);

        $stack->all();
    }

    /**
     * @param list<class-string> $plugins
     *
     * @return LazyPluginStack<Decorator>
     */
    private function stack(array $plugins): LazyPluginStack
    {
        /** @var list<class-string<Decorator>> $plugins */
        return new LazyPluginStack(Decorator::class, $plugins, new Container());
    }
}
