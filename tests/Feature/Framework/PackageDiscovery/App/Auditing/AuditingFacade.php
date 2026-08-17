<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\App\Auditing;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolver\ServiceMap;

/**
 * The consuming application's one module.
 *
 * It names the package's interface, because that is the extension point the
 * package publishes -- and nothing else about the package. There is no
 * registration, no provider, and no line in the application's `gacela.php`.
 *
 * @method AuditingFactory getFactory()
 */
#[ServiceMap(method: 'getFactory', className: AuditingFactory::class)]
final class AuditingFacade extends AbstractFacade
{
    public function announce(string $message): void
    {
        foreach ($this->getFactory()->createChannels() as $channel) {
            $channel->write($message);
        }
    }
}
