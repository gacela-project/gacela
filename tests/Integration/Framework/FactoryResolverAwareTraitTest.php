<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework;

use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\FakeModule\FakeModuleFacade;
use PHPUnit\Framework\TestCase;

final class FactoryResolverAwareTraitTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_child_facade_call(): void
    {
        $facade = new FakeModuleFacade();

        self::assertEquals('key from config', $facade->overrideByChildMethod());
    }

    public function test_parent_facade_call(): void
    {
        $facade = new FakeModuleFacade();

        self::assertEquals('parentMethod', $facade->parentMethod());
    }
}
