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
}
