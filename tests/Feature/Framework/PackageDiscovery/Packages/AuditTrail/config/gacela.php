<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditChannelInterface;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditRecorder;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditSinkInterface;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\FileAuditSink;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\LogAuditChannel;

/**
 * What this package contributes to whatever application installs it.
 *
 * The same shape a project's own `gacela.php` returns, and the same
 * `GacelaConfig` surface: there is no second config format for packages.
 */
return static function (GacelaConfig $config): void {
    // The extension point, and the one member this package ships. An
    // application adding its own channel appends to this stack rather than
    // replacing it.
    $config->addPluginStack(AuditChannelInterface::class, [LogAuditChannel::class]);

    // A default. The application's `gacela.php` is merged after every package,
    // so binding this again there wins.
    $config->addBinding(AuditSinkInterface::class, FileAuditSink::class);

    $config->addAppConfigKeyValue('audit.enabled', true);

    $config->registerSpecificListener(
        GacelaBootstrapFinishedEvent::class,
        static fn (GacelaBootstrapFinishedEvent $event): null => AuditRecorder::record('booted'),
    );
};
