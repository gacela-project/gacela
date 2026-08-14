<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingIfPlugin;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

/**
 * `addBindingIf()` exists for one scenario, named in its own docblock: a package
 * registers a default that the application can override. That scenario needs two
 * config sources to exist at all, and it was only ever exercised on a single
 * `GacelaConfig` -- where `addBinding()` immediately before it is the whole test.
 *
 * `extendGacelaConfig()` is the mechanism the promise rides on: every extending
 * config runs against the *same* `GacelaConfig`, so the package's `bindIf` can
 * see what the application already bound. Nothing else about the arrangement
 * makes that true, which is why it is worth pinning end to end.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_a_package_default_fills_a_binding_the_application_left_open(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->extendGacelaConfig(PackageGacelaConfig::class);
        });

        self::assertSame('package-default', Gacela::get(Clock::class)?->source());
    }

    /**
     * The promise itself. The application binds first and the package's
     * `bindIf` runs afterwards against the same config, so it declines.
     */
    public function test_a_package_default_declines_to_replace_the_applications_binding(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->addBinding(Clock::class, ApplicationClock::class);
            $config->extendGacelaConfig(PackageGacelaConfig::class);
        });

        self::assertSame('application', Gacela::get(Clock::class)?->source());
    }

    /**
     * Order inside the closure does not decide it. Extending configs run after
     * the whole closure has, so stating the binding last works the same -- which
     * matters, because the two lines read as if the second could clobber the
     * first.
     */
    public function test_the_application_binding_wins_whichever_line_comes_first(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->extendGacelaConfig(PackageGacelaConfig::class);
            $config->addBinding(Clock::class, ApplicationClock::class);
        });

        self::assertSame('application', Gacela::get(Clock::class)?->source());
    }

    private function bootstrapWith(callable $setup): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($setup): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $setup($config);
        });
    }
}
