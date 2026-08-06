<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection;

use PHPStan\Testing\TypeInferenceTestCase;

final class GetProvidedDependencyTypeInferenceTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/TypeFixture/ProvidedDependencyTypes.php');
    }

    /**
     * @param mixed ...$args
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataFileAsserts')]
    public function test_file_asserts(string $assertType, string $file, ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/type-inference.neon'];
    }
}
