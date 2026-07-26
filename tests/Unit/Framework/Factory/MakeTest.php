<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Factory;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class MakeTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    public function test_make_autowires_a_class_by_type(): void
    {
        $widget = (new Make\Factory())->createWidget();

        self::assertSame('autowired', $widget->name);
    }

    public function test_make_overrides_constructor_arguments_by_name(): void
    {
        $widget = (new Make\Factory())->createNamedWidget('custom');

        self::assertSame('custom', $widget->name);
    }

    public function test_make_builds_a_fresh_instance_when_params_are_given(): void
    {
        $factory = new Make\Factory();

        self::assertNotSame(
            $factory->createNamedWidget('same'),
            $factory->createNamedWidget('same'),
        );
    }
}
