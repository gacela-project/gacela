<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\ClassResolver\DocBlockService\UseBlockParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

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

    public function test_empty_class_name_does_not_match_the_first_use_statement(): void
    {
        // With an empty class name the needle would degrade to ';', which every
        // `use ...;` line contains, so the parser would wrongly resolve to the
        // first import. An empty class name must resolve to nothing instead.
        $actual = $this->parser->getUseStatement('', $this->phpCode());

        self::assertSame('', $actual);
    }

    public function test_get_class_from_use(): void
    {
        $actual = $this->parser->getUseStatement('ExistingClassInOtherNs', $this->phpCode());

        self::assertSame('\Ns\Test\Other\ExistingClassInOtherNs', $actual);
    }

    /**
     * PSR-12 group imports, which PhpStorm generates, resolved to the caller's
     * own namespace instead of the imported one -- the parser's pattern could
     * not match a `{`, so every grouped import looked like no import at all.
     *
     * Usually that surfaces as a `MissingClassDefinitionException` for a class
     * the file plainly imports. The worse case is silent: when a class of the
     * same short name *does* exist in the caller's namespace, it is injected
     * instead -- the wrong-object failure this parser's own docblock records
     * fighting once already.
     *
     * @param non-empty-string $useBlock
     * @param non-empty-string $askedFor
     */
    #[DataProvider('importFormProvider')]
    public function test_every_import_form_resolves_to_the_imported_class(string $useBlock, string $askedFor): void
    {
        $phpCode = sprintf("<?php\n\nnamespace App\\Wallet;\n\n%s\n\nfinal class Wallet\n{\n}\n", $useBlock);

        self::assertSame(
            '\App\Shared\MoneyService',
            $this->parser->getUseStatement($askedFor, $phpCode),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function importFormProvider(): iterable
    {
        yield 'plain' => ['use App\Shared\MoneyService;', 'MoneyService'];
        yield 'alias' => ['use App\Shared\MoneyService as MS;', 'MS'];
        yield 'group of one' => ['use App\Shared\{MoneyService};', 'MoneyService'];
        yield 'group of several' => ['use App\Shared\{Other, MoneyService};', 'MoneyService'];
        yield 'group with alias' => ['use App\Shared\{MoneyService as MS};', 'MS'];
        yield 'comma list' => ['use App\Other\Thing, App\Shared\MoneyService;', 'MoneyService'];

        // Exactly how a formatter wraps a long group, so the statement spans
        // lines and a line-anchored pattern sees only its first one.
        yield 'group across lines' => [
            "use App\\Shared\\{\n    Other,\n    MoneyService,\n};",
            'MoneyService',
        ];

        yield 'leading backslash' => ['use \App\Shared\MoneyService;', 'MoneyService'];
    }

    /**
     * A grouped import must not answer for a name it does not bring into
     * scope, the way the group's own prefix could if the prefix were treated
     * as an import in its own right.
     */
    public function test_a_group_prefix_is_not_itself_an_import(): void
    {
        $phpCode = "<?php\n\nnamespace App\\Wallet;\n\nuse App\\Shared\\{MoneyService};\n";

        self::assertSame('\App\Wallet\Shared', $this->parser->getUseStatement('Shared', $phpCode));
    }

    /**
     * `use function`/`use const` inside a group import neither, so they cannot
     * answer for a class name that happens to match.
     */
    public function test_a_grouped_function_import_does_not_answer_for_a_class_name(): void
    {
        $phpCode = "<?php\n\nnamespace App\\Wallet;\n\nuse function App\\Shared\\{helper};\n";

        self::assertSame('\App\Wallet\helper', $this->parser->getUseStatement('helper', $phpCode));
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

    /**
     * A short name is matched against the name an import *defines*, not against
     * the end of the line. `Facade` is a perfectly ordinary class name -- a
     * module can call its facade exactly that -- and every neighbouring import
     * of some `*Facade` ends in it.
     *
     * The wrong class came back silently. Nothing failed; another module's
     * facade was simply injected.
     */
    public function test_a_short_name_is_not_matched_against_the_tail_of_another_import(): void
    {
        $phpCode = <<<'PHP'
            <?php

            namespace Ns\Test;

            use Ns\Billing\BillingFacade;

            final class TestClass
            {
            }
            PHP;

        self::assertSame('\Ns\Test\Facade', $this->parser->getUseStatement('Facade', $phpCode));
    }

    /**
     * The same collision through an alias, which is how it is most likely to be
     * written: a command reaching a sibling module renames its facade to say
     * which one it is, and the rename ends in `Facade` too.
     */
    public function test_a_short_name_is_not_matched_against_the_tail_of_an_alias(): void
    {
        $phpCode = <<<'PHP'
            <?php

            namespace Ns\Test;

            use Ns\Other\Facade as OtherModuleFacade;

            final class TestClass
            {
            }
            PHP;

        self::assertSame('\Ns\Test\Facade', $this->parser->getUseStatement('Facade', $phpCode));
    }

    /**
     * An aliased import brings only the alias into scope, so the original short
     * name is not a name this file can use -- it resolves against the namespace
     * like any other unimported one.
     */
    public function test_an_aliased_import_does_not_answer_for_its_original_name(): void
    {
        $phpCode = <<<'PHP'
            <?php

            namespace Ns\Test;

            use Ns\Other\Renamed as SomethingElse;

            final class TestClass
            {
            }
            PHP;

        self::assertSame('\Ns\Test\Renamed', $this->parser->getUseStatement('Renamed', $phpCode));
    }

    /**
     * An import from the global namespace has no separator to take a last
     * segment after, so the whole name is the name it defines.
     */
    public function test_an_import_from_the_global_namespace_defines_its_whole_name(): void
    {
        $phpCode = <<<'PHP'
            <?php

            namespace Ns\Test;

            use ReflectionClass;

            final class TestClass
            {
            }
            PHP;

        self::assertSame('\ReflectionClass', $this->parser->getUseStatement('ReflectionClass', $phpCode));
    }

    /**
     * `use function` and `use const` import neither, so they cannot answer for
     * a class name that happens to match.
     */
    public function test_a_function_import_does_not_answer_for_a_class_name(): void
    {
        $phpCode = <<<'PHP'
            <?php

            namespace Ns\Test;

            use function Ns\Helpers\Formatter;

            final class TestClass
            {
            }
            PHP;

        self::assertSame('\Ns\Test\Formatter', $this->parser->getUseStatement('Formatter', $phpCode));
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
