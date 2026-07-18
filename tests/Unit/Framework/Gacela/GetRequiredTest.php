<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Gacela;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use stdClass;

final class GetRequiredTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_get_required_resolves_and_memoizes_the_instance(): void
    {
        $first = Gacela::getRequired(stdClass::class);
        $second = Gacela::getRequired(stdClass::class);

        self::assertInstanceOf(stdClass::class, $first);
        self::assertSame($first, $second);
    }
}
