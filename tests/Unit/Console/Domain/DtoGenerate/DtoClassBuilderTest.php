<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\DtoGenerate;

use Gacela\Console\Domain\DtoGenerate\DtoClassBuilder;
use Gacela\Framework\Dto\Schema\DtoType;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function strlen;
use function substr;
use function uniqid;

final class DtoClassBuilderTest extends TestCase
{
    /**
     * Free text in a description must not end the docblock early or leave a
     * line without its ` * `: the generated file has to compile.
     */
    public function test_a_description_with_a_comment_marker_and_a_line_break_still_compiles(): void
    {
        $className = 'GacelaTest\Generated\Dto' . uniqid() . '\Schedule';

        $source = (new DtoClassBuilder())->build($className, [
            'interval' => DtoType::int()->describe("Runs every */5 minutes\nin cron syntax"),
        ]);
        eval(substr($source, strlen('<?php')));

        /** @var class-string $className */
        $doc = (string) (new ReflectionMethod($className, 'getInterval'))->getDocComment();
        self::assertStringContainsString('Runs every *\/5 minutes', $doc);
        self::assertStringContainsString("\n     * in cron syntax", $doc);
    }
}
