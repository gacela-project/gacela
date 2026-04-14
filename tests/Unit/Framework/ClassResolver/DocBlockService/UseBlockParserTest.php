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

    public function test_get_class_from_empty_php_code(): void
    {
        $actual = $this->parser->getUseStatement('TestClass', '');

        self::assertSame('', $actual);
    }

    public function test_get_class_from_use(): void
    {
        $actual = $this->parser->getUseStatement('ExistingClassInOtherNs', $this->phpCode());

        self::assertSame('\Ns\Test\Other\ExistingClassInOtherNs', $actual);
    }

    public function test_get_class_in_same_namespace(): void
    {
        $actual = $this->parser->getUseStatement('ExistingClassInSameNs', $this->phpCode());

        self::assertSame('\Ns\Test\ExistingClassInSameNs', $actual);
    }

    public function test_get_class_with_alias(): void
    {
        $actual = $this->parser->getUseStatement('AliasClass', $this->phpCode());

        self::assertSame('\Ns\Test\Other\WithAliasClassInOtherNs', $actual);
    }

    public function test_get_commented_use_with_double_slash_then_uses_current_namespace(): void
    {
        $actual = $this->parser->getUseStatement('CommentedClassInOtherNs', $this->phpCode());

        self::assertSame('\Ns\Test\CommentedClassInOtherNs', $actual);
    }

    public function test_get_commented_use_with_hashtag_then_uses_current_namespace(): void
    {
        $actual = $this->parser->getUseStatement('CommentedClassInAnotherNs', $this->phpCode());

        self::assertSame('\Ns\Test\CommentedClassInAnotherNs', $actual);
    }

    public function test_leading_backslash_in_use_statement_is_normalized_to_single_backslash(): void
    {
        // A fully-qualified `use \Foo\Bar;` must still resolve to `\Foo\Bar`,
        // not `\\Foo\Bar`. The implementation relies on `ltrim($fqcn, '\\')`
        // to strip the leading backslash before re-prefixing it.
        $phpCode = <<<'PHP'
<?php

namespace Ns\Test;

use \Fully\Qualified\LeadingBackslashClass;

final class TestClass
{
}
PHP;

        $actual = $this->parser->getUseStatement('LeadingBackslashClass', $phpCode);

        self::assertSame('\Fully\Qualified\LeadingBackslashClass', $actual);
    }

    public function test_use_statement_matches_on_semicolon_terminated_class_name(): void
    {
        // If the semicolon is dropped from the needle, any line whose class
        // name is a prefix of the target would match first. This test proves
        // the parser anchors on the terminating `;`, not on a bare substring.
        $phpCode = <<<'PHP'
<?php

namespace Ns\Test;

use Ns\Other\UserNameExtension;
use Ns\Target\UserName;

final class TestClass
{
}
PHP;

        $actual = $this->parser->getUseStatement('UserName', $phpCode);

        self::assertSame('\Ns\Target\UserName', $actual);
    }

    public function test_falls_back_to_empty_namespace_when_php_code_has_no_namespace_line(): void
    {
        $phpCode = <<<'PHP'
<?php

final class TestClass
{
}
PHP;

        $actual = $this->parser->getUseStatement('TestClass', $phpCode);

        self::assertSame('\\\\TestClass', $actual);
    }

    private function phpCode(): string
    {
        return <<<'PHP'
<?php 

// namespace FailingCommentedLine\Test;
#namespace FailingCommentedAnotherLine\Test;
namespace Ns\Test;

use Ns\Test\Other\ExistingClassInOtherNs;
use Ns\Test\Other\WithAliasClassInOtherNs as AliasClass;
//use Ns\Test\Other\CommentedClassInOtherNs;
# use Ns\Test\Other\CommentedClassInAnotherNs;
use Ns\Test\Duplicated\ExistingClassInOtherNs; // this will be ignored. The first match will win.
                                               // this is also illegal in real code. I place it here 
                                               // just to verify the actual logic.
final class TestClass
{
    public function foo(): void 
    {
    }
}
PHP;
    }
}
