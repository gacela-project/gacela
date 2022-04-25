<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\ClassResolver\DocBlockService\UseBlockParser;
use PHPUnit\Framework\TestCase;

final class UseBlockParserTest extends TestCase
{
    private UseBlockParser $parser;

    protected function setUp(): void
    {
        $this->parser = new UseBlockParser();
    }

    public function test_get_class_from_empty_doc_block(): void
    {
        $actual = $this->parser->getUseStatement('TestClass', '');

        self::assertSame('', $actual);
    }


    public function test_get_class_from_method_not_found(): void
    {
        $actual = $this->parser->getUseStatement('NonExistingClass', $this->phpCode());

        self::assertSame('', $actual);
    }

    public function test_get_class_from_method(): void
    {
        $actual = $this->parser->getUseStatement('ExistingClass', $this->phpCode());

        self::assertSame('Ns\Test\Inner\ExistingClass', $actual);
    }

    private function phpCode(): string
    {
        return <<<'PHP'
<?php 
namespace ns\test;

use Ns\Test\Inner\ExistingClass;
use Ns\Test\OuterTwo\ExistingClass; // this will be ignored. The first match will win.
                                    // this is also illegal in real code. I place it here 
                                    // just to verify the actual logic.

final class TestClass
{
    public function foo(): void 
    {
        echo ExistingClass::class;
    }
}
PHP;
    }
}
