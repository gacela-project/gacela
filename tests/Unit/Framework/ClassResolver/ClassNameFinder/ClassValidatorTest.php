<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ClassValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        ClassValidator::resetCache();
    }

    public function test_returns_true_for_existing_class(): void
    {
        $validator = new ClassValidator();

        self::assertTrue($validator->isClassNameValid(stdClass::class));
    }

    public function test_returns_false_for_missing_class(): void
    {
        $validator = new ClassValidator();

        self::assertFalse($validator->isClassNameValid('GacelaTest\\Does\\Not\\Exist'));
    }

    public function test_repeated_lookups_are_memoized(): void
    {
        $validator = new ClassValidator();

        $first = $validator->isClassNameValid('GacelaTest\\Memoized\\Missing');
        $second = $validator->isClassNameValid('GacelaTest\\Memoized\\Missing');

        self::assertFalse($first);
        self::assertSame($first, $second);
    }
}
