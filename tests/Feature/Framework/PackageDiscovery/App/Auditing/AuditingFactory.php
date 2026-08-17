<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\App\Auditing;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Plugins\PluginStack;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditChannelInterface;

final class AuditingFactory extends AbstractFactory
{
    /**
     * @return PluginStack<AuditChannelInterface>
     */
    public function createChannels(): PluginStack
    {
        return $this->getPluginStack(AuditChannelInterface::class);
    }
}
