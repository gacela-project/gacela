<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\CommandArguments;

use Gacela\Console\Domain\CommandArguments\CommandArguments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandArgumentsTest extends TestCase
{
    /**
     * The module name prefixes every generated class, so it has to be one a
     * class can carry: the namespace's, whatever the directory is called.
     */
    #[DataProvider('modules')]
    public function test_the_module_name_is_the_last_namespace_segment(string $namespace, string $directory, string $name): void
    {
        self::assertSame($name, (new CommandArguments($namespace, $directory))->basename());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function modules(): iterable
    {
        yield 'a directory named like the module' => ['App\Product', 'src/Product', 'Product'];
        yield 'a psr-4 root no class can be named after' => ['Acme\Billing', 'modules/billing-core', 'Billing'];
        yield 'a module at a psr-4 root' => ['App', 'src', 'App'];
    }
}
