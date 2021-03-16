<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Todo;

use Gacela\Config;
use PHPUnit\Framework\TestCase;

final class TodoTest extends TestCase
{
    public function setUp(): void
    {
        Config::$applicationRootDir = __DIR__;
        Config::init();
    }

    public function testExportCommandMultiple(): void
    {
        self::assertEquals(1, 1);
    }
}
