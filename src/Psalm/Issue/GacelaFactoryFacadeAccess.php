<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A Factory calling `$this->getFacade()`.
 *
 * Same-module access goes through the Factory itself; cross-module access goes
 * through the Provider.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaFactoryFacadeAccess" errorLevel="suppress"/>
 * ```
 */
final class GacelaFactoryFacadeAccess extends PluginIssue
{
}
