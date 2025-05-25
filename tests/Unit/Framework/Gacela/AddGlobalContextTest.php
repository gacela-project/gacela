<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Gacela;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class AddGlobalContextTest extends TestCase
{
    public function test_add_global_uses_caller_file_as_context(): void
    {
        Gacela::addGlobal(new class() extends AbstractConfig {});

        $context = basename(__FILE__, '.php');
        $key = '\\' . ClassInfo::MODULE_NAME_ANONYMOUS . '\\' . $context . '\\Config';

        self::assertInstanceOf(AbstractConfig::class, AnonymousGlobal::getByKey($key));
    }
}
