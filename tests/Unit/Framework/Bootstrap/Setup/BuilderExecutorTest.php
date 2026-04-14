<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\Setup\BuilderExecutor;
use Gacela\Framework\Bootstrap\Setup\Properties;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use PHPUnit\Framework\TestCase;

final class BuilderExecutorTest extends TestCase
{
    public function test_build_bindings_merges_setup_external_services_with_passed_ones(): void
    {
        $received = null;

        $properties = new Properties();
        $properties->externalServices = ['setup-service' => 'setupValue'];
        $properties->bindingsFn = static function (BindingsBuilder $builder, array $services) use (&$received): void {
            $received = $services;
        };

        $executor = new BuilderExecutor($properties);
        $executor->buildBindings(new BindingsBuilder(), ['passed-service' => 'passedValue']);

        self::assertSame(
            ['setup-service' => 'setupValue', 'passed-service' => 'passedValue'],
            $received,
        );
    }
}
