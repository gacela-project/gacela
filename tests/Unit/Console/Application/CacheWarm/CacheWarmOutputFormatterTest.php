<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\CacheWarm;

use Gacela\Console\Application\CacheWarm\CacheWarmOutputFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class CacheWarmOutputFormatterTest extends TestCase
{
    public function test_module_discovery_warning_surfaces_the_underlying_error(): void
    {
        $output = new BufferedOutput();
        $formatter = new CacheWarmOutputFormatter($output);

        $formatter->writeModuleDiscoveryWarning('composer.json is not readable');

        $text = $output->fetch();
        self::assertStringContainsString('Some modules could not be discovered', $text);
        self::assertStringContainsString('composer.json is not readable', $text);
    }
}
