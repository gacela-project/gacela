<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Testing\Fixture;

use Gacela\Framework\Testing\ContainerSnapshot;

/**
 * Calls every protected ContainerFixture method from a subclass of the class
 * that composes the trait, guaranteeing the methods stay protected (not private).
 */
final class ChildOfContainerFixtureUser extends ParentWithContainerFixture
{
    public function doResetContainer(): void
    {
        $this->resetContainer();
    }

    public function doResetGacelaSingletons(): void
    {
        $this->resetGacelaSingletons();
    }

    public function doCaptureContainerState(): ContainerSnapshot
    {
        return $this->captureContainerState();
    }

    public function doRestoreContainerState(ContainerSnapshot $snapshot): void
    {
        $this->restoreContainerState($snapshot);
    }

    public function doContainerTempDir(): string
    {
        return $this->containerTempDir();
    }

    public function doCleanupContainerTempDirs(): void
    {
        $this->cleanupContainerTempDirs();
    }
}
