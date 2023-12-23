<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingAbstractGacelaClassesByDefault;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    private const FACADE_ROOT_DIR = 'tests' . DIRECTORY_SEPARATOR . 'Feature' . DIRECTORY_SEPARATOR
                                    . 'Framework' . DIRECTORY_SEPARATOR . 'UsingAbstractGacelaClassesByDefault';

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_missing_factory_and_config(): void
    {
        $facade = new Module\Facade();

        self::assertStringContainsString(self::FACADE_ROOT_DIR, $facade->getAppRootDir());
    }
}
