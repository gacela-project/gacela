<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\ClassResolver\DocBlockService\DocBlockParser;
use PHPUnit\Framework\TestCase;

final class DocBlockParserTest extends TestCase
{
    private DocBlockParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DocBlockParser();
    }

    public function test_get_class_from_empty_doc_block(): void
    {
        $input = '';
        self::assertSame('', $this->parser->getClassFromMethod($input, 'getClass()'));
    }

    public function test_get_class_from_method(): void
    {
        $input = <<<TXT
/**
 * @method \App\Module\ClassName getClass()
 */
TXT;
        self::assertSame(
            '\App\Module\ClassName',
            $this->parser->getClassFromMethod($input, 'getClass()'),
        );
    }

    public function test_get_factory_falls_back_to_abstract_factory_when_template_is_missing(): void
    {
        self::assertSame(
            \Gacela\Framework\AbstractFactory::class,
            $this->parser->getClassFromMethod('/** nothing relevant */', 'getFactory'),
        );
    }

    public function test_get_factory_resolves_abstract_facade_template_parameter(): void
    {
        $docBlock = <<<'TXT'
/**
 * @extends \Gacela\Framework\AbstractFacade<\App\Module\CustomFactory>
 */
TXT;

        self::assertSame(
            '\App\Module\CustomFactory',
            $this->parser->getClassFromMethod($docBlock, 'getFactory'),
        );
    }

    public function test_get_factory_matches_abstract_facade_template_case_insensitively(): void
    {
        // Proves the /i flag on the extends regex — without it, `@EXTENDS`
        // would not match and the parser would fall back to AbstractFactory.
        $docBlock = <<<'TXT'
/**
 * @EXTENDS \Gacela\Framework\AbstractFacade<\App\Module\CaseFactory>
 */
TXT;

        self::assertSame(
            '\App\Module\CaseFactory',
            $this->parser->getClassFromMethod($docBlock, 'getFactory'),
        );
    }

    public function test_get_config_falls_back_to_abstract_config_when_template_is_missing(): void
    {
        self::assertSame(
            \Gacela\Framework\AbstractConfig::class,
            $this->parser->getClassFromMethod('/** nothing relevant */', 'getConfig'),
        );
    }

    public function test_get_config_resolves_abstract_factory_template_parameter(): void
    {
        $docBlock = <<<'TXT'
/**
 * @extends \Gacela\Framework\AbstractFactory<\App\Module\CustomConfig>
 */
TXT;

        self::assertSame(
            '\App\Module\CustomConfig',
            $this->parser->getClassFromMethod($docBlock, 'getConfig'),
        );
    }

    public function test_get_config_matches_abstract_factory_template_case_insensitively(): void
    {
        // Same /i-flag proof as the facade variant.
        $docBlock = <<<'TXT'
/**
 * @EXTENDS \Gacela\Framework\AbstractFactory<\App\Module\CaseConfig>
 */
TXT;

        self::assertSame(
            '\App\Module\CaseConfig',
            $this->parser->getClassFromMethod($docBlock, 'getConfig'),
        );
    }

    public function test_unknown_method_with_no_matching_doc_block_returns_empty_string(): void
    {
        self::assertSame('', $this->parser->getClassFromMethod('/** nothing */', 'somethingElse'));
    }
}
