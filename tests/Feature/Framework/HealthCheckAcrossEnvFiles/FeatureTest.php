<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\HealthCheckAcrossEnvFiles;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Gacela\Framework\Health\HealthCheckRegistry;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_ENV');
        HealthCheckRegistry::reset();
    }

    public function test_health_checks_from_default_and_env_file_are_both_registered(): void
    {
        putenv('APP_ENV=dev');

        $this->bootstrapGacela();

        self::assertSame(
            [HealthCheckA::class, HealthCheckB::class],
            HealthCheckRegistry::all(),
        );
    }

    public function test_health_checks_do_not_accumulate_across_bootstraps(): void
    {
        putenv('APP_ENV=dev');

        $this->bootstrapGacela();
        $this->bootstrapGacela();

        self::assertSame(
            [HealthCheckA::class, HealthCheckB::class],
            HealthCheckRegistry::all(),
        );
    }

    private function bootstrapGacela(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }
}
